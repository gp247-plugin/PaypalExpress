<?php

namespace App\GP247\Plugins\PaypalExpress\Services;

use GP247\Shop\Payment\Contracts\PaymentGateway;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\PaymentCurrency;
use GP247\Shop\Payment\PaymentRequestService;
use GP247\Shop\Payment\Support\CollectResult;
use GP247\Shop\Payment\Support\RefundResult;

/**
 * PayPal as a gateway of the core's payment requests: an Orders v2 order for what is
 * still due (collect — captured when the payer comes back) and a refund of a capture it
 * made (refund).
 *
 * Every call uses the PayPal account of the store that OWNS the request
 * (NFR-SEC-paypal-express-payment-request-store-credentials).
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-express-payment-request
 * @aidlc-adr paypal-express_payment-request-gateway
 */
class PaypalPaymentRequestGateway implements PaymentGateway
{
    public const KEY = 'PaypalExpress';

    public function key(): string
    {
        return self::KEY;
    }

    public function capabilities(): array
    {
        return [self::CAP_COLLECT, self::CAP_REFUND];
    }

    /**
     * Usable when the plugin is on for the request's store and that store has credentials
     * for its current mode.
     *
     * @param PaymentRequest $request
     * @return bool
     */
    public function availableFor(PaymentRequest $request): bool
    {
        // Same rule as checkout: the plugin may be switched off for this store.
        if (function_exists('gp247_plugin_store_enabled') && !gp247_plugin_store_enabled(self::KEY, $request->store_id)) {
            return false;
        }

        return (new PaypalService($request->store_id))->configured();
    }

    /**
     * Create the PayPal order and hand back the approval link.
     *
     * @param PaymentRequest $request
     * @return CollectResult
     * @throws \RuntimeException
     */
    public function collect(PaymentRequest $request): CollectResult
    {
        $order = (new PaypalService($request->store_id))->createRequestOrder(
            $request,
            paypalexpress_payment_request_return_url(),
            (string) app(PaymentRequestService::class)->publicUrl($request)
        );

        $approve = null;
        foreach ((array) ($order['links'] ?? []) as $link) {
            if (in_array($link['rel'] ?? '', ['approve', 'payer-action'], true)) {
                $approve = (string) ($link['href'] ?? '');
                break;
            }
        }
        if (empty($order['id']) || empty($approve)) {
            throw new \RuntimeException('PayPal create order returned no approval link');
        }

        return new CollectResult($approve, (string) $order['id']);
    }

    /**
     * Refund part or all of a capture this gateway made.
     *
     * WHY the number of earlier refunds in PayPal-Request-Id: the same click sent twice
     * returns the same refund, while a second legitimate refund gets its own.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $original
     * @param float           $amount
     * @param string|null     $idempotencyKey Issued by the core for a refund source (not a stored movement).
     * @return RefundResult
     * @throws \RuntimeException
     */
    public function refund(PaymentRequest $request, PaymentMovement $original, float $amount, ?string $idempotencyKey = null): RefundResult
    {
        $minor = (int) round($amount * (10 ** PaymentCurrency::precision((string) $request->currency)));
        $idempotencyKey ??= 'gp247-payreq-refund-' . $original->id . '-' . app(PaymentRequestService::class)->refundsOf($original)->count() . '-' . $minor;

        $refund = (new PaypalService($request->store_id))->refundCapture(
            (string) $original->gateway_ref,
            $amount,
            (string) $request->currency,
            $idempotencyKey
        );

        $status = strtoupper((string) ($refund['status'] ?? ''));
        if (empty($refund['id']) || in_array($status, ['FAILED', 'CANCELLED'], true)) {
            throw new \RuntimeException('PayPal refused the refund (' . ($status ?: 'no id') . ')');
        }

        return new RefundResult((string) $refund['id'], $status === 'COMPLETED');
    }
}
