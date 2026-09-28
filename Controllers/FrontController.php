<?php
#App\GP247\Plugins\PaypalExpress\Controllers\FrontController.php
namespace App\GP247\Plugins\PaypalExpress\Controllers;

use App\GP247\Plugins\PaypalExpress\AppConfig;
use App\GP247\Plugins\PaypalExpress\Services\PaypalService;
use App\GP247\Plugins\PaypalExpress\Services\PaypalWebhookService;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Controllers\ShopCartController;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use Illuminate\Http\Request;

/**
 * Storefront endpoints of the PayPal plugin: hand the shopper to PayPal, capture on
 * return, cancel, and receive PayPal webhooks. Money is written only through the core
 * ledger (recordPayment / recordRefund) and order status only through changeStatus().
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-record-payment-into-ledger
 * @aidlc-story US-paypal-express-security-hardening
 * @aidlc-adr paypal-express_webhook-outside-storefront-middleware
 */
class FrontController extends RootFrontController
{
    public AppConfig $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = new AppConfig;
    }

    public function index()
    {
        //Nothing
    }

    /**
     * Translated shopper-facing message of this plugin.
     *
     * @param string $key
     * @return string
     */
    private function lang(string $key): string
    {
        return (string) trans('Plugins/PaypalExpress::lang.' . $key);
    }

    /**
     * Redirect the shopper home with an error message.
     *
     * @param string $key Lang key.
     * @return \Illuminate\Http\RedirectResponse
     */
    private function failHome(string $key)
    {
        return redirect(gp247_route_front('front.home'))->with('error', $this->lang($key));
    }

    /**
     * Called by the shop right after the order is saved (session `orderID`): create the
     * PayPal order from the SAVED order and send the shopper to PayPal to approve it.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-security-hardening
     */
    public function processOrder()
    {
        $orderId = session('orderID');
        $order = $orderId ? ShopOrder::find($orderId) : null;
        if (!$order) {
            gp247_report('PayPal Process Order - no order in session');
            return $this->failHome('error_missing_order');
        }
        if ($order->isLocked() || (float) $order->received >= (float) $order->total) {
            gp247_report('PayPal Process Order - order ' . $order->id . ' is locked or already paid');
            return $this->failHome('error_missing_order');
        }

        try {
            $result = (new PaypalService)->createOrder($order);
        } catch (\Throwable $e) {
            gp247_report('PayPal Process Order - order ' . $order->id . ': ' . $e->getMessage());
            return $this->failHome('error_create_failed');
        }

        $paypalOrderId = (string) ($result['id'] ?? '');
        $approvalUrl = null;
        foreach ($result['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
                $approvalUrl = (string) $link['href'];
                break;
            }
        }
        if ($paypalOrderId === '' || $approvalUrl === null) {
            gp247_report('PayPal Process Order - order ' . $order->id . ': PayPal answered without id/approve link');
            return $this->failHome('error_create_failed');
        }

        // The token is compared on return: only the PayPal order created in THIS checkout
        // session may be captured for this order.
        session(['paypalToken' => $paypalOrderId]);

        // Dont use header('Location: ...'): the session would be lost.
        return redirect()->away($approvalUrl);
    }

    /**
     * Return URL after approval: capture the payment, write it to the ledger, move the
     * order status through the core seam, then finish the checkout.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-record-payment-into-ledger
     * @aidlc-story US-paypal-express-security-hardening
     */
    public function capturePayment()
    {
        $token = (string) request()->input('token', '');
        $payerId = (string) request()->input('PayerID', '');
        $orderId = session('orderID');
        $order = $orderId ? ShopOrder::find($orderId) : null;

        if ($token === '' || $payerId === '') {
            gp247_report('PayPal Capture Payment - order ' . ($orderId ?? '?') . ': missing approval parameters');
            return $this->failHome('error_missing_params');
        }
        if (!$order) {
            gp247_report('PayPal Capture Payment - no order in session');
            return $this->failHome('error_missing_order');
        }
        if (!hash_equals((string) session('paypalToken', ''), $token)) {
            gp247_report('PayPal Capture Payment - order ' . $order->id . ': token does not match this checkout');
            return $this->failHome('error_invalid_token');
        }
        if ($order->isLocked() || (float) $order->received >= (float) $order->total) {
            // Already settled (webhook, admin, or a replayed return URL): just finish.
            gp247_report('PayPal Capture Payment - order ' . $order->id . ' already paid or locked, capture skipped');
            session()->forget('paypalToken');
            return (new ShopCartController)->completeOrder();
        }

        try {
            $result = (new PaypalService)->captureOrder($token);
        } catch (\Throwable $e) {
            gp247_report('PayPal Capture Payment - order ' . $order->id . ': ' . $e->getMessage());
            return $this->failHome('error_capture_failed');
        }

        if (($result['status'] ?? '') !== 'COMPLETED') {
            // Keep the session so the shopper can retry once PayPal completes.
            gp247_report('PayPal Capture Payment - order ' . $order->id . ': status ' . ($result['status'] ?? 'unknown'));
            return $this->failHome('error_not_completed');
        }

        session()->forget('paypalToken');
        $this->recordCapture($order, $result);

        return (new ShopCartController)->completeOrder();
    }

    /**
     * Write a COMPLETED capture to the order: the ledger row, the gateway reference and
     * the configured order status (through the seam).
     *
     * WHY record the money and not just a status: until the ledger existed a successful
     * capture only set flags, so a paid order still carried received = 0 and never
     * entered revenue. recordPayment() is idempotent on the capture id, so a replayed
     * callback cannot double-count (ADR shop_order-payment-ledger).
     *
     * @param ShopOrder $order
     * @param array<string, mixed> $result Decoded capture response.
     * @return void
     */
    private function recordCapture(ShopOrder $order, array $result): void
    {
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captureId = isset($capture['id']) ? (string) $capture['id'] : null;
        $captured = (float) ($capture['amount']['value'] ?? 0);
        $capturedCurrency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));

        $matchesOrder = $captured > 0
            && abs($captured - (float) $order->total) < 0.01
            && ($capturedCurrency === '' || $capturedCurrency === strtoupper((string) $order->currency));

        if ($captured > 0) {
            // Record what PayPal ACTUALLY took, never the order total "to make it match":
            // a disagreement is a reconciliation problem to surface, not to hide.
            $order->recordPayment(
                $captured,
                $this->plugin->configKey,
                $captureId,
                null,
                $matchesOrder ? null : 'PayPal captured ' . $captured . ' ' . $capturedCurrency
                    . ' but the order is ' . $order->total . ' ' . $order->currency
            );
            if (!$matchesOrder) {
                gp247_report('PayPal Capture Payment - order ' . $order->id . ': captured ' . $captured . ' ' . $capturedCurrency
                    . ' differs from order ' . $order->total . ' ' . $order->currency);
            }
        }

        $order->update(['transaction' => $captureId]);

        $order = ShopOrder::find($order->id);
        $target = (int) gp247_config($this->plugin->configKey . '_order_status_success');
        $history = [
            'content' => 'Transaction ' . $captureId,
            'customer_id' => $order->customer_id ?: 0,
        ];
        if ($target > 0) {
            $order->changeStatus($target, $history);
        } else {
            $order->addOrderHistory($history + ['order_id' => $order->id, 'order_status_id' => $order->status]);
        }
    }

    /**
     * Cancel URL from PayPal. Only a NEW order without money may be cancelled from here:
     * this is a plain GET anyone holding the checkout session can open, so it must never
     * undo a payment or an order the admin already handles.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-security-hardening
     */
    public function cancelPayment()
    {
        $orderId = session('orderID');
        $order = $orderId ? ShopOrder::find($orderId) : null;

        if ($order !== null && (float) $order->received > 0) {
            // Paid after all: finish the checkout instead of undoing it.
            return (new ShopCartController)->completeOrder();
        }
        if ($order !== null && (int) $order->status !== ShopOrderStatus::NEW) {
            gp247_report('PayPal Cancel - refused for order ' . $order->id . ' in status ' . $order->status);
            return $this->failHome('error_cannot_cancel');
        }

        return (new ShopCartController)->cancelOrder();
    }

    /**
     * PayPal webhook (bare route, see Route.php). Verifies the transmission with PayPal
     * from the RAW body, answers 400 to anything unverified, 200 once journaled, 500 only
     * on an unexpected error so PayPal retries.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-security-hardening
     * @aidlc-adr paypal-express_webhook-outside-storefront-middleware
     */
    public function handleWebhook(Request $request)
    {
        try {
            $body = (string) $request->getContent();
            $verified = (new PaypalService)->verifyWebhookSignature([
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'cert_url' => $request->header('paypal-cert-url'),
                'webhook_id' => paypalexpress_config('webhook_id'),
                'event_body' => $body,
            ]);
            if (!$verified) {
                return response('Invalid signature', 400);
            }

            $event = json_decode($body, true);
            if (!is_array($event)) {
                return response('Invalid payload', 400);
            }

            return (new PaypalWebhookService)->processWebhook($event)
                ? response('Webhook processed successfully', 200)
                : response('Error processing webhook', 500);
        } catch (\Throwable $e) {
            gp247_report('PayPal Webhook - unexpected error: ' . $e->getMessage());
            return response('Internal server error', 500);
        }
    }
}
