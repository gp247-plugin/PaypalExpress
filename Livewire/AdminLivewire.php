<?php
#App\GP247\Plugins\PaypalExpress\Livewire\AdminLivewire.php

namespace App\GP247\Plugins\PaypalExpress\Livewire;

use App\GP247\Plugins\PaypalExpress\AppConfig;
use GP247\Core\AdminShell\Infrastructure\ConfigForm;
use GP247\Core\AdminShell\Infrastructure\ReadinessCheck;
use GP247\Core\Models\AdminConfig;
use GP247\Shop\Payment\PaymentSettings;
use GP247\Shop\Models\ShopCurrency;
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
 * the site/marketplace owner (root admin) reaches this screen. Above the form, "Check
 * before use" lists what still stops shoppers from paying with PayPal (readiness(),
 * local settings only).
 *
 * @aidlc-unit plugin-paypal-express
 * @aidlc-story US-paypal-express-per-store-credentials
 * @aidlc-story US-paypal-express-config-readiness
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

    /** Currencies the PayPal REST API accepts for payments (developer.paypal.com → Currency codes). */
    private const PAYPAL_CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR',
        'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD',
    ];

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
                    'events' => 'PAYMENT.CAPTURE.REFUNDED, PAYMENT.CAPTURE.COMPLETED',
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
            'events' => 'PAYMENT.CAPTURE.REFUNDED, PAYMENT.CAPTURE.COMPLETED',
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

    /**
     * What the store still needs before shoppers can pay with PayPal: the payment step at
     * checkout, the plugin on for the store, the client id + secret of the mode in use,
     * an active currency PayPal accepts, and — so refunds made on PayPal reach the order —
     * the webhook id and a public webhook URL. Sandbox mode is a warning. Local settings
     * only: the credentials themselves are only checked by PayPal at payment time.
     *
     * @return array<int, ReadinessCheck>
     *
     * @aidlc-unit plugin-paypal-express
     * @aidlc-story US-paypal-express-config-readiness
     * @aidlc-story US-admin-shell-config-form-readiness
     */
    protected function readiness(): array
    {
        $store = $this->readinessStoreId();
        $t = static fn (string $key, array $replace = []): string => (string) trans('Plugins/PaypalExpress::lang.' . $key, $replace);
        // WHY '' = sandbox: an unsaved row falls back to config.php, whose default is sandbox.
        $sandbox = $this->saved(self::PREFIX . 'sandbox', $store) !== '0';
        $env = $sandbox ? 'sandbox' : 'live';
        $section = $t('section_' . $env);
        $webhookUrl = function_exists('paypalexpress_webhook_url') ? paypalexpress_webhook_url() : url('plugin/paypal-express/webhook');
        $credentialsMissing = $this->saved(self::PREFIX . 'client_id_' . $env, $store) === ''
            || $this->saved(self::PREFIX . 'client_secret_' . $env, $store) === '';

        return [
            ...$this->shopChecks($store, $t),
            ReadinessCheck::failIf($credentialsMissing, 'paypal_client', $t('ready_client_missing', ['mode' => $section]),
                ReadinessCheck::ERROR, '', $section, $t('ready_client_ok', ['mode' => $section])),
            $this->currencyCheck($t),
            ReadinessCheck::failIf($this->saved(self::PREFIX . 'webhook_id', $store) === '', 'paypal_webhook_id', $t('ready_webhook_id_missing'),
                ReadinessCheck::WARNING, '', $t('section_webhook'), $t('ready_webhook_id_ok')),
            ReadinessCheck::failIf($sandbox, 'paypal_sandbox', $t('ready_sandbox_on'),
                ReadinessCheck::WARNING, '', $t('section_mode'), $t('ready_sandbox_off')),
            ReadinessCheck::failIf(self::isLocalHost((string) parse_url($webhookUrl, PHP_URL_HOST)), 'paypal_webhook_public', $t('ready_webhook_local'),
                ReadinessCheck::WARNING, '', '', $t('ready_webhook_ok')),
        ];
    }

    /**
     * Shoppers pay in the currency they chose, so every active currency PayPal refuses is a
     * shopper who cannot use PayPal; none accepted means nobody can.
     *
     * @param \Closure $t Translator of this plugin's strings.
     * @return ReadinessCheck
     */
    private function currencyCheck(\Closure $t): ReadinessCheck
    {
        $active = ShopCurrency::where('status', 1)->pluck('code')
            ->map(static fn ($code): string => strtoupper((string) $code))->unique()->values()->all();
        $refused = array_values(array_diff($active, self::PAYPAL_CURRENCIES));
        $codes = ['codes' => implode(', ', $refused)];
        $fixUrl = self::adminUrl('admin_currency.index');
        if ($active !== [] && count($refused) === count($active)) {
            return ReadinessCheck::error('paypal_currency', $t('ready_currency_none', $codes), $fixUrl, $t('fix_currency'));
        }
        if ($refused !== []) {
            return ReadinessCheck::warning('paypal_currency', $t('ready_currency_some', $codes), $fixUrl, $t('fix_currency'));
        }

        return ReadinessCheck::ok('paypal_currency', $t('ready_currency_ok'));
    }

    /**
     * The shop-side conditions — the payment step at checkout and this plugin on for the
     * store — from gp247/shop, so every payment plugin shows the same thing. An older
     * gp247/shop without PaymentSettings::readiness() gets the same two checks computed here.
     *
     * @param string   $store Store being checked.
     * @param \Closure $t     Translator of this plugin's strings.
     * @return array<int, ReadinessCheck> payment_enabled, provider_enabled.
     *
     * @aidlc-story US-payment-request-plugin-readiness-check
     */
    private function shopChecks(string $store, \Closure $t): array
    {
        if (method_exists(PaymentSettings::class, 'readiness')) {
            return PaymentSettings::readiness($store, 'PaypalExpress');
        }

        return [
            ReadinessCheck::failIf(in_array($this->saved('use_payment', $store), ['', '0'], true), 'payment_enabled', $t('ready_payment_off'),
                ReadinessCheck::ERROR, self::adminUrl('admin_config.index'), $t('fix_order_tab'), $t('ready_payment_on')),
            ReadinessCheck::failIf($this->saved('PaypalExpress', $store, 'Plugins') !== '1', 'provider_enabled', $t('ready_plugin_off'),
                ReadinessCheck::ERROR, self::adminUrl('admin_plugin.index'), $t('fix_plugins'), $t('ready_plugin_on')),
        ];
    }

    /**
     * A setting read straight from admin_config: the store row, else the GLOBAL row.
     *
     * WHY not gp247_config(): it caches the rows for the whole request, so right after a
     * save the box would still show the values from before it.
     *
     * @param string      $key   admin_config key.
     * @param string      $store Store id.
     * @param string|null $group admin_config group (null = any).
     * @return string Trimmed value, '' when unset.
     */
    private function saved(string $key, string $store, ?string $group = null): string
    {
        $global = (string) (defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0);
        foreach (array_unique([$store, $global]) as $scope) {
            $row = AdminConfig::where('key', $key)->where('store_id', $scope)
                ->when($group !== null, static fn ($q) => $q->where('group', $group))->first();
            if ($row !== null && trim((string) $row->value) !== '') {
                return trim((string) $row->value);
            }
        }

        return '';
    }

    /**
     * @param string $route Admin route name.
     * @return string URL, or '' when the route is not registered.
     */
    private static function adminUrl(string $route): string
    {
        return \Illuminate\Support\Facades\Route::has($route) ? (string) gp247_route_admin($route) : '';
    }

    /**
     * @param string $host Host of the webhook URL.
     * @return bool PayPal cannot call it back (local name or private address).
     */
    private static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost') {
            return true;
        }
        foreach (['.local', '.localhost', '.test', '.internal', '.lan'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }
}
