<?php
#App\GP247\Plugins\PaypalExpress\Livewire\AdminLivewire.php

namespace App\GP247\Plugins\PaypalExpress\Livewire;

use GP247\Core\AdminShell\Infrastructure\ConfigForm;
use GP247\Core\Models\AdminConfig;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopPaymentStatus;

/**
 * Admin settings screen for the Paypal Express plugin: PayPal connection
 * credentials (client id/secret sandbox+live, webhook id, sandbox toggle) plus
 * the order/payment status mapping for success and refund events, backed by the
 * admin_config key/value table.
 *
 * Per-store + secret-at-rest (ADR paypal-express_per-store-credentials): the
 * client secrets are `password` fields, so core encrypts them at rest and never
 * reveals an inherited value at a sub-store scope. storeScope is "platform"
 * (gp247.json): only the site/marketplace owner (root admin) reaches this screen —
 * store-admins/vendors are blocked by the fence because Provider.php does NOT
 * register this plugin's admin segment. Root still gets the per-store picker
 * (storeScoped()=true): multi-store = a PayPal account per store; marketplace =
 * stores inherit the platform's GLOBAL account.
 */
class AdminLivewire extends ConfigForm
{
    protected ?string $permission = null;

    /** Credential keys (group "Plugins", prefixed with the plugin key). */
    private const CREDENTIAL_KEYS = [
        'PaypalExpress_sandbox' => '1',
        'PaypalExpress_client_id_sandbox' => '',
        'PaypalExpress_client_secret_sandbox' => '',
        'PaypalExpress_client_id_live' => '',
        'PaypalExpress_client_secret_live' => '',
        'PaypalExpress_webhook_id' => '',
    ];

    /** Secret credential keys — encrypted at rest (admin_config.security = 1). */
    private const SECRET_KEYS = [
        'PaypalExpress_client_secret_sandbox',
        'PaypalExpress_client_secret_live',
    ];

    /**
     * Seed the credential rows at GLOBAL for installs made before they existed, so the
     * form is never missing a field. Empty by default (secrets flagged, not yet set);
     * the .env-import migration fills real values on upgrade.
     *
     * @return void
     */
    public function mount(): void
    {
        $global = defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0;
        foreach (self::CREDENTIAL_KEYS as $key => $default) {
            AdminConfig::firstOrCreate(
                ['group' => 'Plugins', 'key' => $key, 'store_id' => $global],
                [
                    'code' => 'PaypalExpress_config',
                    'sort' => 0,
                    'value' => $default,
                    'security' => in_array($key, self::SECRET_KEYS, true) ? 1 : 0,
                    'detail' => 'Plugins/PaypalExpress::lang.' . substr($key, strlen('PaypalExpress_')),
                ]
            );
        }

        parent::mount();
    }

    /**
     * Opt into per-store scope so the root admin gets the store picker (multi-store:
     * a PayPal account per store; marketplace: stores inherit the platform GLOBAL account).
     *
     * @return bool
     */
    protected function storeScoped(): bool
    {
        return true;
    }

    /**
     * Kept as "Plugins" (not the configKey) on purpose: these 4 rows have been
     * seeded under this group by AppConfig::install() since plugin version 1.0,
     * so already-installed sites keep their configured values across the
     * upgrade instead of losing them to a new empty group.
     */
    protected function group(): string
    {
        return 'Plugins';
    }

    protected function heading(): string
    {
        return trans('Plugins/PaypalExpress::lang.config_paypal');
    }

    protected function keys(): array
    {
        return [
            'PaypalExpress_sandbox',
            'PaypalExpress_client_id_sandbox',
            'PaypalExpress_client_secret_sandbox',
            'PaypalExpress_client_id_live',
            'PaypalExpress_client_secret_live',
            'PaypalExpress_webhook_id',
            'PaypalExpress_order_status_success',
            'PaypalExpress_payment_status_success',
            'PaypalExpress_order_status_refunded',
            'PaypalExpress_payment_status_refunded',
        ];
    }

    protected function fieldTypes(): array
    {
        return [
            // Connection: secrets are `password` -> encrypted at rest + never revealed.
            // client_id / webhook_id are public-ish identifiers -> plain text.
            'PaypalExpress_sandbox' => 'bool',
            'PaypalExpress_client_secret_sandbox' => 'password',
            'PaypalExpress_client_secret_live' => 'password',
            // Status mapping.
            'PaypalExpress_order_status_success' => 'select',
            'PaypalExpress_payment_status_success' => 'select',
            'PaypalExpress_order_status_refunded' => 'select',
            'PaypalExpress_payment_status_refunded' => 'select',
        ];
    }

    protected function fieldOptions(): array
    {
        $orderStatus = ShopOrderStatus::getIdAll();
        $paymentStatus = ShopPaymentStatus::getIdAll();

        return [
            'PaypalExpress_order_status_success' => $orderStatus,
            'PaypalExpress_payment_status_success' => $paymentStatus,
            'PaypalExpress_order_status_refunded' => $orderStatus,
            'PaypalExpress_payment_status_refunded' => $paymentStatus,
        ];
    }
}
