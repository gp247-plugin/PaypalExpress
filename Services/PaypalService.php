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

    /**
     * @param string|int|null $storeId Use this store's PayPal account instead of the effective
     *                                 store's (a core payment request is served with its own store's).
     */
    public function __construct($storeId = null)
    {
        // WHY paypalexpress_config(): credentials are resolved per effective store
        // (multi-store: the store's own PayPal account; marketplace: the platform's, via
        // GLOBAL fallback), decrypted at read (ADR paypal-express_per-store-credentials).
        if (paypalexpress_config('sandbox', null, $storeId)) {
            $this->clientId = (string) paypalexpress_config('client_id_sandbox', null, $storeId);
            $this->clientSecret = (string) paypalexpress_config('client_secret_sandbox', null, $storeId);
            $this->baseUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->clientId = (string) paypalexpress_config('client_id_live', null, $storeId);
            $this->clientSecret = (string) paypalexpress_config('client_secret_live', null, $storeId);
            $this->baseUrl = 'https://api-m.paypal.com';
        }
    }

    /**
     * Whether credentials exist for the current mode.
     *
     * @return bool
     */
    public function configured(): bool
    {
        return trim($this->clientId) !== '' && trim($this->clientSecret) !== '';
    }

    /**
     * Create a PayPal order (intent CAPTURE) for what is still due on a core payment request.
     *
     * @param \GP247\Shop\Payment\Models\PaymentRequest $request
     * @param string $returnUrl
     * @param string $cancelUrl
     * @return array<string, mixed> Decoded PayPal order (id, status, links…).
     * @throws \RuntimeException
     *
     * @aidlc-story US-paypal-express-payment-request
     */
    public function createRequestOrder($request, string $returnUrl, string $cancelUrl): array
    {
        $currency = strtoupper((string) $request->currency);
        $reference = 'payreq-' . $request->id;
        $description = trim((string) $request->description) !== ''
            ? mb_substr((string) $request->description, 0, 127)
            : 'Payment request #' . $request->id;

        return $this->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'custom_id' => $reference,
                'description' => $description,
                'amount' => ['currency_code' => $currency, 'value' => $this->amount($request->outstanding(), $currency)],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
            ],
        ], 'create order');
    }

    /**
     * Refund part or all of a capture.
     *
     * @param string $captureId
     * @param float  $amount
     * @param string $currency
     * @param string $requestId PayPal-Request-Id: the same id returns the same refund.
     * @return array<string, mixed> Decoded refund (id, status…).
     * @throws \RuntimeException
     *
     * @aidlc-story US-paypal-express-payment-request
     */
    public function refundCapture(string $captureId, float $amount, string $currency, string $requestId): array
    {
        if (!preg_match('/^[A-Za-z0-9\-_]{1,64}$/', $captureId)) {
            throw new \RuntimeException('PayPal refund refused: malformed capture id');
        }
        $currency = strtoupper($currency);

        return $this->post('/v2/payments/captures/' . $captureId . '/refund', [
            'amount' => ['value' => $this->amount($amount, $currency), 'currency_code' => $currency],
        ], 'refund', ['PayPal-Request-Id' => $requestId]);
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
     * @param array<string, string> $headers Extra headers (e.g. PayPal-Request-Id).
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function post(string $path, array $payload, string $step, array $headers = []): array
    {
        try {
            $data = $this->api()->withHeaders($headers)->post($path, $payload)->throw()->json();
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
