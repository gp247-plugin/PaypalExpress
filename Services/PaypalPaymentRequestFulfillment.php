<?php

namespace App\GP247\Plugins\PaypalExpress\Services;

use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\PaymentRequestService;

/**
 * Turns what PayPal reports about a core payment request (not an order) into movements
 * of that request: a completed capture (return page or PAYMENT.CAPTURE.COMPLETED) and a
 * refund (PAYMENT.CAPTURE.REFUNDED). Idempotent on the capture / refund id.
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-express-payment-request
 * @aidlc-adr paypal-express_payment-request-gateway
 */
class PaypalPaymentRequestFulfillment
{
    private const PREFIX = 'payreq-';

    /**
     * @return bool Whether the core has payment requests at all.
     */
    public static function supported(): bool
    {
        return class_exists(PaymentRequestService::class);
    }

    /**
     * The request id a PayPal `custom_id` / `reference_id` names, or null.
     *
     * @param mixed $customId
     * @return string|null
     */
    public static function requestIdOf($customId): ?string
    {
        $customId = (string) $customId;

        return str_starts_with($customId, self::PREFIX) && ctype_digit(substr($customId, strlen(self::PREFIX)))
            ? substr($customId, strlen(self::PREFIX))
            : null;
    }

    /**
     * Record a completed capture on its request.
     *
     * @param PaymentRequest       $request
     * @param array<string, mixed> $capture       Capture object (id, status, amount, custom_id).
     * @param string|null          $paypalOrderId The PayPal order it belongs to.
     * @return PaymentMovement|null Null when the capture is not a completed one of this request.
     */
    public function recordCapture(PaymentRequest $request, array $capture, ?string $paypalOrderId): ?PaymentMovement
    {
        if (strtoupper((string) ($capture['status'] ?? 'COMPLETED')) !== 'COMPLETED'
            || self::requestIdOf($capture['custom_id'] ?? null) !== (string) $request->id
            || empty($capture['id'])) {
            return null;
        }
        $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? $request->currency));
        $note = $currency === strtoupper((string) $request->currency)
            ? null
            : 'PayPal captured in ' . $currency . ' but the request is in ' . $request->currency;
        if ($note !== null) {
            gp247_report('PayPal: payment request ' . $request->id . ': ' . $note);
        }

        return app(PaymentRequestService::class)->recordMovement($request, [
            'type' => PaymentMovement::TYPE_COLLECT,
            'amount' => (float) ($capture['amount']['value'] ?? 0),
            'gateway' => PaypalPaymentRequestGateway::KEY,
            'gateway_ref' => (string) $capture['id'],
            'method' => PaymentMovement::METHOD_GATEWAY,
            'reference' => $paypalOrderId,
            'note' => $note,
            'confirmed' => true,
        ]);
    }

    /**
     * PAYMENT.CAPTURE.COMPLETED for a payment request (the return page may have missed it).
     *
     * @param array<string, mixed> $capture Webhook resource.
     * @return bool|null Null when the capture is not a payment request's.
     */
    public function captureCompleted(array $capture): ?bool
    {
        $id = self::requestIdOf($capture['custom_id'] ?? null);
        if ($id === null || !self::supported()) {
            return null;
        }
        $request = PaymentRequest::find($id);
        if ($request === null) {
            gp247_report('PayPal Webhook - capture ' . ($capture['id'] ?? '?') . ' names payment request ' . $id . ' which does not exist');
            return false;
        }
        $orderId = $capture['supplementary_data']['related_ids']['order_id'] ?? null;

        return $this->recordCapture($request, $capture, $orderId !== null ? (string) $orderId : null) !== null;
    }

    /**
     * PAYMENT.CAPTURE.REFUNDED for a capture of a payment request.
     *
     * @param array<string, mixed> $refund    Webhook resource (refund).
     * @param string               $captureId The capture it gives back.
     * @return bool|null Null when the capture is not a payment request's (the order path handles it).
     */
    public function captureRefunded(array $refund, string $captureId): ?bool
    {
        if (!self::supported()) {
            return null;
        }
        $service = app(PaymentRequestService::class);
        $original = $service->findGatewayCollect(PaypalPaymentRequestGateway::KEY, $captureId);
        if ($original === null) {
            return null;
        }
        if (empty($refund['id'])) {
            return false;
        }
        $request = PaymentRequest::find($original->request_id);
        $service->recordMovement($request, [
            'type' => PaymentMovement::TYPE_REFUND,
            'amount' => (float) ($refund['amount']['value'] ?? 0),
            'gateway' => PaypalPaymentRequestGateway::KEY,
            'gateway_ref' => (string) $refund['id'],
            'method' => PaymentMovement::METHOD_GATEWAY,
            'refund_of' => $original->id,
            'confirmed' => true,
        ]);

        return true;
    }
}
