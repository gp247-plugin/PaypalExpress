<?php
#App\GP247\Plugins\PaypalExpress\Livewire\AdminLivewire.php

namespace App\GP247\Plugins\PaypalExpress\Livewire;

use App\GP247\Plugins\PaypalExpress\AppConfig;
use GP247\Core\AdminShell\Infrastructure\ConfigForm;
use GP247\Core\Models\AdminConfig;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopPaymentStatus;

/**
 * Admin settings screen for the Paypal Express plugin: PayPal connection
 * credentials (client id/secret sandbox+live, webhook id, sandbox toggle) plus
 * the order/payment status mapping for success and refund events, backed by the
 * admin_config key/value table. Every field carries a hint saying where the value
 * comes from in PayPal Developer and what it does.
 *
 * Per-store + secret-at-rest (ADR paypal-express_per-store-credentials): the
 * client secrets are `password` fields, so core encrypts them at rest and never
 * reveals an inherited value at a sub-store scope. storeScope is "store"
 * (gp247.json): credentials CAN differ per store (storeScoped()=true gives the root
 * admin the store picker — multi-store = a PayPal account per store; marketplace =
 * stores inherit the owner's GLOBAL account). Who may edit is a separate knob:
 * Provider.php deliberately does NOT register this plugin's admin segment in
 * store_scoped_segments, so store-admins/vendors are blocked by the fence and only
 * the site/marketplace owner (root admin) reaches this screen.
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-express-per-store-credentials
 * @aidlc-adr paypal-express_per-store-credentials
 */
class AdminLivewire extends ConfigForm
{
    protected ?string $permission = null;

    private const PREFIX = 'PaypalExpress_';

    /** Credential keys (group "Plugins", prefixed with the plugin key) => default. */
    private const CREDENTIAL_KEYS = [
        'sandbox' => '1',
        'client_id_sandbox' => '',
        'client_secret_sandbox' => '',
        'client_id_live' => '',
        'client_secret_live' => '',
        'webhook_id' => '',
    ];

    /** Secret credential keys — encrypted at rest (admin_config.security = 1). */
    private const SECRET_KEYS = ['client_secret_sandbox', 'client_secret_live'];

    /**
     * Seed the credential rows at GLOBAL for installs made before they existed, so the
     * form is never missing a field, and give every row its display position (a site
     * upgraded from 3.1.2 or earlier still has every row at sort 0).
     *
     * @return void
     */
    public function mount(): void
    {
        $global = defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0;
        foreach (self::CREDENTIAL_KEYS as $key => $default) {
            AdminConfig::firstOrCreate(
                ['group' => 'Plugins', 'key' => self::PREFIX . $key, 'store_id' => (string) $global],
                [
                    'code' => 'PaypalExpress_config',
                    'sort' => AppConfig::SORT[$key],
                    'value' => $default,
                    'security' => in_array($key, self::SECRET_KEYS, true) ? 1 : 0,
                    'detail' => 'Plugins/PaypalExpress::lang.' . $key,
                ]
            );
        }
        AppConfig::convergeSort();

        parent::mount();
    }

    /**
     * Opt into per-store scope so the root admin gets the store picker (multi-store:
     * a PayPal account per store; marketplace: stores inherit the owner's GLOBAL account).
     *
     * @return bool
     */
    protected function storeScoped(): bool
    {
        return true;
    }

    /**
     * Kept as "Plugins" (not the configKey) on purpose: these rows have been seeded
     * under this group by AppConfig::install() since plugin version 1.0, so
     * already-installed sites keep their configured values across the upgrade.
     */
    protected function group(): string
    {
        return 'Plugins';
    }

    protected function heading(): string
    {
        return trans('Plugins/PaypalExpress::lang.config_paypal');
    }

    /**
     * Keys in display order (the same order as AppConfig::SORT).
     *
     * @return array<int, string>
     */
    protected function keys(): array
    {
        return array_map(fn ($key) => self::PREFIX . $key, array_keys(AppConfig::SORT));
    }

    protected function fieldTypes(): array
    {
        return [
            // Connection: secrets are `password` -> encrypted at rest + never revealed.
            // client_id / webhook_id are public-ish identifiers -> plain text.
            self::PREFIX . 'sandbox' => 'bool',
            self::PREFIX . 'client_secret_sandbox' => 'password',
            self::PREFIX . 'client_secret_live' => 'password',
            // Status mapping.
            self::PREFIX . 'order_status_success' => 'select',
            self::PREFIX . 'payment_status_success' => 'select',
            self::PREFIX . 'order_status_refunded' => 'select',
            self::PREFIX . 'payment_status_refunded' => 'select',
        ];
    }

    protected function fieldOptions(): array
    {
        $orderStatus = ShopOrderStatus::getIdAll();
        $paymentStatus = ShopPaymentStatus::getIdAll();

        return [
            self::PREFIX . 'order_status_success' => $orderStatus,
            self::PREFIX . 'payment_status_success' => $paymentStatus,
            self::PREFIX . 'order_status_refunded' => $orderStatus,
            self::PREFIX . 'payment_status_refunded' => $paymentStatus,
        ];
    }

    /**
     * Group the fields into titled blocks (core seam ConfigForm::sections(), optional:
     * an older core without it renders the flat table). The Sandbox and Live blocks are
     * parallel key sets, so the block in use carries a badge that follows the toggle.
     *
     * @return array<int, array{id: string, title: string, hint?: string, badge?: string, keys: array<int, string>}>
     */
    protected function sections(): array
    {
        $t = fn (string $key, array $replace = []) => (string) trans('Plugins/PaypalExpress::lang.' . $key, $replace);
        $sandbox = (bool) ($this->values[self::PREFIX . 'sandbox'] ?? true);
        $inUse = $t('section_in_use');

        return [
            [
                'id' => 'mode',
                'title' => $t('section_mode'),
                'hint' => $t('section_mode_hint'),
                'keys' => [self::PREFIX . 'sandbox'],
            ],
            [
                'id' => 'sandbox',
                'title' => $t('section_sandbox'),
                'hint' => $t('section_sandbox_hint'),
                'badge' => $sandbox ? $inUse : '',
                'keys' => [self::PREFIX . 'client_id_sandbox', self::PREFIX . 'client_secret_sandbox'],
            ],
            [
                'id' => 'live',
                'title' => $t('section_live'),
                'hint' => $t('section_live_hint'),
                'badge' => $sandbox ? '' : $inUse,
                'keys' => [self::PREFIX . 'client_id_live', self::PREFIX . 'client_secret_live'],
            ],
            [
                'id' => 'webhook',
                'title' => $t('section_webhook'),
                'hint' => $t('section_webhook_hint', [
                    'url' => function_exists('paypalexpress_webhook_url') ? paypalexpress_webhook_url() : url('plugin/paypal-express/webhook'),
                    'events' => 'PAYMENT.CAPTURE.REFUNDED',
                ]),
                'keys' => [self::PREFIX . 'webhook_id'],
            ],
            [
                'id' => 'order-status',
                'title' => $t('section_order_status'),
                'keys' => [self::PREFIX . 'order_status_success', self::PREFIX . 'order_status_refunded'],
            ],
            [
                'id' => 'payment-status',
                'title' => $t('section_payment_status'),
                'hint' => $t('config_hint_payment_status'),
                'keys' => [self::PREFIX . 'payment_status_success', self::PREFIX . 'payment_status_refunded'],
            ],
        ];
    }

    /**
     * Where each value comes from in PayPal Developer and what it does on the shop.
     *
     * @return array<string, string>
     */
    protected function fieldHints(): array
    {
        $t = fn (string $key, array $replace = []) => (string) trans('Plugins/PaypalExpress::lang.' . $key, $replace);
        $webhook = $t('config_hint_webhook_id', [
            'url' => function_exists('paypalexpress_webhook_url') ? paypalexpress_webhook_url() : url('plugin/paypal-express/webhook'),
            'events' => 'PAYMENT.CAPTURE.REFUNDED',
        ]);

        return [
            self::PREFIX . 'sandbox' => $t('config_hint_sandbox'),
            self::PREFIX . 'client_id_sandbox' => $t('config_hint_client_id', ['env' => 'Sandbox']),
            self::PREFIX . 'client_secret_sandbox' => $t('config_hint_client_secret', ['env' => 'Sandbox']),
            self::PREFIX . 'client_id_live' => $t('config_hint_client_id', ['env' => 'Live']),
            self::PREFIX . 'client_secret_live' => $t('config_hint_client_secret', ['env' => 'Live']),
            self::PREFIX . 'webhook_id' => $webhook,
            self::PREFIX . 'order_status_success' => $t('config_hint_order_status_success'),
            self::PREFIX . 'order_status_refunded' => $t('config_hint_order_status_refunded'),
            self::PREFIX . 'payment_status_success' => $t('config_hint_payment_status'),
            self::PREFIX . 'payment_status_refunded' => $t('config_hint_payment_status'),
        ];
    }
}
