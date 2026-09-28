<?php

namespace App\GP247\Plugins\PaypalExpress\Services;

use GP247\Shop\Models\ShopOrder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Thin REST client for the PayPal Orders v2 / Webhooks v1 APIs, built on Laravel's HTTP
 * client (no PayPal SDK). Credentials are resolved per effective store at construction
 * (ADR paypal-express_per-store-credentials); one instance serves one request.
 *
 * Error messages raised here name the step and the HTTP status only — never the
 * credentials, the shopper or the raw PayPal body (NFR-SEC-paypal-express-log-no-pii).
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-express-security-hardening
 * @aidlc-adr paypal-express_webhook-outside-storefront-middleware
 */
class PaypalService
{
    /** Currencies PayPal takes as whole units (no decimals). */
    private const ZERO_DECIMAL = ['HUF', 'JPY', 'TWD'];

    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct()
    {
        // WHY paypalexpress_config(): credentials are resolved per effective store
        // (multi-store: the store's own PayPal account; marketplace: the platform's, via
        // GLOBAL fallback), decrypted at read (ADR paypal-express_per-store-credentials).
        if (paypalexpress_config('sandbox')) {
            $this->clientId = (string) paypalexpress_config('client_id_sandbox');
            $this->clientSecret = (string) paypalexpress_config('client_secret_sandbox');
            $this->baseUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->clientId = (string) paypalexpress_config('client_id_live');
            $this->clientSecret = (string) paypalexpress_config('client_secret_live');
            $this->baseUrl = 'https://api-m.paypal.com';
        }
    }

    /**
     * A request builder for the PayPal API, authenticated with the OAuth token.
     *
     * @return PendingRequest
     */
    private function api(): PendingRequest
    {
        if ($this->accessToken === null) {
            $this->accessToken = $this->fetchAccessToken();
        }

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->withToken($this->accessToken);
    }

    /**
     * Obtain a client-credentials access token from PayPal.
     *
     * @return string
     * @throws \RuntimeException When PayPal refuses the credentials or is unreachable.
     */
    private function fetchAccessToken(): string
    {
        try {
            $data = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('PayPal token request failed (HTTP ' . $e->response->status() . ')', 0, $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('PayPal token request failed: ' . $e->getMessage(), 0, $e);
        }

        $token = (string) ($data['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('PayPal token request returned no access token');
        }

        return $token;
    }

    /**
     * Create a PayPal order (intent CAPTURE) for a saved shop order.
     *
     * WHY the saved order and not the checkout session: the order row is the single source
     * of truth for what the shopper owes; session figures can be stale or absent.
     *
     * @param ShopOrder $order Saved order with its `details` lines.
     * @return array<string, mixed> Decoded PayPal order (id, status, links…).
     * @throws \RuntimeException When PayPal rejects the order or is unreachable.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-security-hardening
     */
    public function createOrder(ShopOrder $order): array
    {
        $currency = (string) $order->currency;
        $money = fn ($value) => ['currency_code' => $currency, 'value' => $this->amount((float) $value, $currency)];

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order->id,
                'description' => 'Order ID: ' . $order->id,
                'amount' => $money($order->total) + [
                    'breakdown' => [
                        'item_total' => $money($order->subtotal),
                        'tax_total' => $money($order->tax ?? 0),
                        'discount' => $money($order->discount ?? 0),
                        'handling' => $money($order->other_fee ?? 0),
                        'shipping' => $money($order->shipping ?? 0),
                    ],
                ],
                'items' => $this->lineItems($order, $currency),
            ]],
            'application_context' => [
                // Computed from the plugin route so the redirect matches the store's
                // current domain (no stored URL to drift). See function.php.
                'return_url' => paypalexpress_return_url(),
                'cancel_url' => paypalexpress_cancel_url(),
            ],
        ];

        return $this->post('/v2/checkout/orders', $payload, 'create order');
    }

    /**
     * Capture an approved PayPal order.
     *
     * @param string $paypalOrderId The PayPal order id (the `token` on the return URL).
     * @return array<string, mixed> Decoded capture result (status, purchase_units…).
     * @throws \RuntimeException When PayPal rejects the capture or is unreachable.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-record-payment-into-ledger
     */
    public function captureOrder(string $paypalOrderId): array
    {
        if (!preg_match('/^[A-Za-z0-9\-_]{1,64}$/', $paypalOrderId)) {
            throw new \RuntimeException('PayPal capture refused: malformed order token');
        }

        return $this->post('/v2/checkout/orders/' . $paypalOrderId . '/capture', [], 'capture');
    }

    /**
     * Ask PayPal whether a webhook transmission is genuine.
     *
     * Fail-closed: any missing input, transport error or non-SUCCESS answer yields false.
     * The caller must reject the event on false.
     *
     * @param array<string, mixed> $webhookData transmission_id, transmission_time, transmission_sig, cert_url, webhook_id, event_body (raw JSON string).
     * @return bool True only when PayPal answers verification_status = SUCCESS.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-security-hardening
     * @aidlc-adr paypal-express_webhook-outside-storefront-middleware
     */
    public function verifyWebhookSignature(array $webhookData): bool
    {
        foreach (['transmission_id', 'webhook_id', 'transmission_time', 'event_body', 'transmission_sig', 'cert_url'] as $param) {
            if (empty($webhookData[$param])) {
                gp247_report('PayPal Webhook - verification skipped, missing ' . $param);
                return false;
            }
        }

        $event = json_decode((string) $webhookData['event_body'], true);
        if (!is_array($event)) {
            gp247_report('PayPal Webhook - verification skipped, body is not JSON');
            return false;
        }

        try {
            $result = $this->post('/v1/notifications/verify-webhook-signature', [
                'transmission_id' => $webhookData['transmission_id'],
                'transmission_time' => $webhookData['transmission_time'],
                'webhook_id' => $webhookData['webhook_id'],
                'webhook_event' => $event,
                'transmission_sig' => $webhookData['transmission_sig'],
                'cert_url' => $webhookData['cert_url'],
                'auth_algo' => 'SHA256withRSA',
            ], 'verify webhook signature');
        } catch (\Throwable $e) {
            gp247_report('PayPal Webhook - ' . $e->getMessage());
            return false;
        }

        return ($result['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * POST a JSON payload to the PayPal API and decode the answer.
     *
     * @param string $path
     * @param array<string, mixed> $payload
     * @param string $step Human label for error messages (no data).
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function post(string $path, array $payload, string $step): array
    {
        try {
            $data = $this->api()->post($path, $payload)->throw()->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('PayPal ' . $step . ' failed (HTTP ' . $e->response->status() . ')', 0, $e);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('PayPal ' . $step . ' failed: ' . $e->getMessage(), 0, $e);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Line items for the PayPal order, one per saved order line.
     *
     * @param ShopOrder $order
     * @param string $currency
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(ShopOrder $order, string $currency): array
    {
        return $order->details->map(function ($line) use ($currency) {
            return [
                'name' => mb_substr((string) $line->name, 0, 127),
                'sku' => mb_substr((string) ($line->sku ?? ''), 0, 127),
                'quantity' => (string) (int) $line->qty,
                'unit_amount' => ['currency_code' => $currency, 'value' => $this->amount((float) $line->price, $currency)],
            ];
        })->values()->all();
    }

    /**
     * Format an amount the way PayPal expects for the currency.
     *
     * @param float $value
     * @param string $currency
     * @return string
     */
    private function amount(float $value, string $currency): string
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true)
            ? (string) (int) round($value)
            : number_format($value, 2, '.', '');
    }
}
