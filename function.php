<?php

if (!function_exists('paypalexpress_config')) {
    /**
     * Resolve a PayPal setting for the current effective store.
     *
     * Order: the admin_config row for the effective store (gp247_plugin_store_id) → the
     * GLOBAL row (two-tier gp247_config) → the plugin's config.php static default. From
     * version 3.1 the database is the single runtime source of truth; config.php holds
     * only static defaults and no longer reads .env (a legacy .env is migrated into the
     * database once by AppConfig::update()). Secrets stored in admin_config are decrypted
     * transparently by core's read choke, so this returns plaintext.
     *
     * @param string          $key     Setting key without the "PaypalExpress_" prefix (e.g. "client_secret_live").
     * @param mixed           $default Fallback default when neither the database nor config.php has a value.
     * @param string|int|null $storeId Resolve for this store instead of the effective one (a payment
     *                                 request is served with ITS store's account).
     * @return mixed The resolved value.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-per-store-credentials
     * @aidlc-adr paypal-express_per-store-credentials
     */
    function paypalexpress_config(string $key, $default = null, $storeId = null)
    {
        $storeId ??= function_exists('gp247_plugin_store_id')
            ? gp247_plugin_store_id()
            : (defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0);

        // DB: effective store -> GLOBAL (gp247_config resolves the two tiers).
        $value = gp247_config('PaypalExpress_' . $key, $storeId);

        if ($value === null || $value === '') {
            // Static default from config.php (no .env at runtime).
            $value = config('Plugins/PaypalExpress.' . $key, $default);
        }

        return $value;
    }
}

if (!function_exists('paypalexpress_return_url')) {
    /**
     * Absolute URL PayPal redirects to after a successful payment.
     *
     * Computed per request from the plugin route so it matches the store's current domain
     * (and any locale prefix) instead of being a stored value that can drift when the
     * domain changes — correct for both multi-store (per-domain) and marketplace topologies.
     *
     * @return string Absolute capture-payment URL.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-per-store-credentials
     * @aidlc-adr paypal-express_per-store-credentials
     */
    function paypalexpress_return_url(): string
    {
        return route('paypal-express.capture_payment');
    }
}

if (!function_exists('paypalexpress_cancel_url')) {
    /**
     * Absolute URL PayPal redirects to when the customer cancels the payment.
     *
     * Computed per request from the plugin route (see paypalexpress_return_url()).
     *
     * @return string Absolute cancel-payment URL.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-per-store-credentials
     * @aidlc-adr paypal-express_per-store-credentials
     */
    function paypalexpress_cancel_url(): string
    {
        return route('paypal-express.cancel_payment');
    }
}

if (!function_exists('paypalexpress_webhook_url')) {
    /**
     * Absolute URL the site owner registers as a webhook endpoint in PayPal Developer.
     *
     * @return string Absolute webhook URL for the current domain.
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-per-store-credentials
     */
    function paypalexpress_webhook_url(): string
    {
        return route('paypal-express.webhook');
    }
}

if (!function_exists('paypalexpress_payment_request_return_url')) {
    /**
     * Where PayPal sends a payer back after approving a core payment request.
     *
     * @return string
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-payment-request
     */
    function paypalexpress_payment_request_return_url(): string
    {
        return \Illuminate\Support\Facades\Route::has('paypal-express.payment_request.return')
            ? route('paypal-express.payment_request.return')
            : url('plugin/paypal-express/payment-request/return');
    }
}

if (!function_exists('paypalexpress_register_payment_gateway')) {
    /**
     * Offer PayPal to the core's payment requests (pay links, refunds) — only when the
     * core has that feature; an older core simply never sees the gateway.
     *
     * @return void
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-payment-request
     * @aidlc-adr paypal-express_payment-request-gateway
     */
    function paypalexpress_register_payment_gateway(): void
    {
        if (!interface_exists(\GP247\Shop\Payment\Contracts\PaymentGateway::class)) {
            return;
        }
        // WHY a whole-array write: dot-notation would split a gateway key containing dots.
        $gateways = (array) config('gp247-config.payment.gateways', []);
        $gateways['PaypalExpress'] = [
            'label' => 'PayPal',
            'capabilities' => ['collect', 'refund'],
            'driver' => \App\GP247\Plugins\PaypalExpress\Services\PaypalPaymentRequestGateway::class,
        ];
        config(['gp247-config.payment.gateways' => $gateways]);
    }
}
