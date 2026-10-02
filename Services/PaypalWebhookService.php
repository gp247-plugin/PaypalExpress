<?php

namespace App\GP247\Plugins\PaypalExpress\Services;

use App\GP247\Plugins\PaypalExpress\Models\PaypalWebhook;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;

/**
 * Turns a verified PayPal webhook event into ledger rows and order status changes,
 * going only through the core seams (recordRefund / changeStatus). Every event is
 * journaled in paypal_webhooks and deduplicated on its event id.
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-record-payment-into-ledger
 * @aidlc-story US-paypal-express-security-hardening
 * @aidlc-adr paypal-express_webhook-outside-storefront-middleware
 */
class PaypalWebhookService
{
    private const PLUGIN_KEY = 'PaypalExpress';

    /**
     * Journal a verified event and schedule its processing after the response.
     *
     * @param array<string, mixed> $webhookData Decoded event body.
     * @return bool True when the event was accepted (new or already seen).
     */
    public function processWebhook(array $webhookData): bool
    {
        try {
            $eventId = isset($webhookData['id']) ? (string) $webhookData['id'] : null;
            $eventType = isset($webhookData['event_type']) ? (string) $webhookData['event_type'] : '';

            if ($eventType === '') {
                gp247_report('PayPal Webhook - event ' . ($eventId ?? '?') . ' has no event type');
                return false;
            }

            if ($eventId !== null && PaypalWebhook::where('event_id', $eventId)->exists()) {
                // Acknowledge: PayPal resends until it gets a 200.
                return true;
            }

            $webhook = PaypalWebhook::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'resource_id' => isset($webhookData['resource']['id']) ? (string) $webhookData['resource']['id'] : null,
                'resource_type' => isset($webhookData['resource_type']) ? (string) $webhookData['resource_type'] : null,
                'status' => 'pending',
                'payload' => $webhookData,
            ]);

            // WHY afterResponse and not a queued job: PayPal only needs the 200 quickly, and
            // the platform must work without a queue worker (NFR-AVAIL-paypal-express-no-queue).
            dispatch(function () use ($webhook) {
                $this->processWebhookJob($webhook);
            })->afterResponse();

            return true;
        } catch (\Throwable $e) {
            gp247_report('PayPal Webhook - journaling failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply one journaled event.
     *
     * @param PaypalWebhook $webhook
     * @return void
     */
    public function processWebhookJob(PaypalWebhook $webhook): void
    {
        try {
            switch ($webhook->event_type) {
                case 'PAYMENT.CAPTURE.REFUNDED':
                    $this->handlePaymentCaptureRefunded($webhook);
                    break;
                case 'PAYMENT.CAPTURE.COMPLETED':
                    // Only a core payment request's capture (custom_id payreq-<id>): an
                    // order's capture is recorded on its return URL as before.
                    $resource = is_array($webhook->payload['resource'] ?? null) ? $webhook->payload['resource'] : [];
                    (new PaypalPaymentRequestFulfillment)->captureCompleted($resource);
                    break;
                default:
                    // Not an event this plugin acts on; the capture itself is recorded on the return URL.
                    break;
            }
            $webhook->markAsProcessed();
        } catch (\Throwable $e) {
            gp247_report('PayPal Webhook - event ' . ($webhook->event_id ?? $webhook->id) . ' (' . $webhook->event_type . ') failed: ' . $e->getMessage());
            $webhook->markAsFailed($e->getMessage());
        }
    }

    /**
     * PAYMENT.CAPTURE.REFUNDED: record the refunded amount and move the order status.
     *
     * WHY record the AMOUNT: flipping the whole order to "refunded" was wrong for any
     * partial refund. The ledger holds the real figure and the payment status follows the
     * money that is left (ADR shop_order-payment-ledger). A refund on a cancelled order is
     * money only: re-entering a status would take the returned stock back.
     *
     * @param PaypalWebhook $webhook
     * @return void
     */
    private function handlePaymentCaptureRefunded(PaypalWebhook $webhook): void
    {
        $payload = $webhook->payload;
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $captureId = $this->captureIdFrom($resource);

        if ($captureId === '') {
            gp247_report('PayPal Webhook - event ' . ($webhook->event_id ?? '?') . ': no capture id in refund');
            return;
        }

        $order = ShopOrder::where('transaction', $captureId)->first();
        if (!$order && (new PaypalPaymentRequestFulfillment)->captureRefunded($resource, $captureId) !== null) {
            return; // a refund of a core payment request's capture
        }
        if (!$order) {
            gp247_report('PayPal Webhook - event ' . ($webhook->event_id ?? '?') . ': no order for capture ' . $captureId);
            return;
        }

        $refunded = (float) ($resource['amount']['value'] ?? 0);
        $currency = strtoupper((string) ($resource['amount']['currency_code'] ?? ''));
        $refundId = isset($resource['id']) ? (string) $resource['id'] : null;

        $note = $this->reconciliationNote($order, $refunded, $currency);
        if ($note !== null) {
            gp247_report('PayPal Webhook - order ' . $order->id . ': ' . $note);
        }

        if ($refunded > 0) {
            $order->recordRefund($refunded, self::PLUGIN_KEY, $refundId, null, $note ?? ('PayPal refund ' . $currency));
        }

        $history = [
            'content' => 'Payment refunded via PayPal. Amount: ' . $refunded . ' ' . $currency,
            'customer_id' => $order->customer_id ?: 0,
        ];
        $order = ShopOrder::find($order->id);
        if ((int) $order->status === ShopOrderStatus::CANCELED) {
            $order->addOrderHistory($history + ['order_id' => $order->id, 'order_status_id' => $order->status]);
            return;
        }

        $target = (int) gp247_config(self::PLUGIN_KEY . '_order_status_refunded');
        if ($target > 0) {
            $order->changeStatus($target, $history);
        } else {
            $order->addOrderHistory($history + ['order_id' => $order->id, 'order_status_id' => $order->status]);
        }
    }

    /**
     * The capture a refund belongs to: `capture_id`, else the `up` link.
     *
     * @param array<string, mixed> $resource
     * @return string Empty when not found.
     */
    private function captureIdFrom(array $resource): string
    {
        $captureId = (string) ($resource['capture_id'] ?? '');
        if ($captureId !== '') {
            return $captureId;
        }
        foreach ($resource['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'up' && str_contains((string) ($link['href'] ?? ''), '/captures/')) {
                $parts = explode('/captures/', (string) $link['href']);
                return (string) end($parts);
            }
        }

        return '';
    }

    /**
     * A note when the refund does not reconcile with the order (currency differs, or the
     * refund exceeds what was received). Null when everything matches.
     *
     * @param ShopOrder $order
     * @param float $refunded
     * @param string $currency
     * @return string|null
     */
    private function reconciliationNote(ShopOrder $order, float $refunded, string $currency): ?string
    {
        $orderCurrency = strtoupper((string) $order->currency);
        if ($currency !== '' && $orderCurrency !== '' && $currency !== $orderCurrency) {
            return 'PayPal refunded ' . $refunded . ' ' . $currency . ' but the order is in ' . $orderCurrency;
        }
        if ($refunded > 0 && $refunded - (float) $order->received > 0.01) {
            return 'PayPal refunded ' . $refunded . ' ' . $currency . ' but only ' . (float) $order->received . ' was received';
        }

        return null;
    }
}
