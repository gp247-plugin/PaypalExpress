<?php
#App\GP247\Plugins\PaypalExpress\Livewire\AdminLivewire.php

namespace App\GP247\Plugins\PaypalExpress\Livewire;

use GP247\Core\AdminShell\Infrastructure\ConfigForm;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopPaymentStatus;

/**
 * Admin settings screen for the Paypal Express plugin (order/payment status
 * mapping for success and refund events), backed by the admin_config
 * key/value table.
 */
class AdminLivewire extends ConfigForm
{
    protected ?string $permission = null;

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
            'PaypalExpress_order_status_success',
            'PaypalExpress_payment_status_success',
            'PaypalExpress_order_status_refunded',
            'PaypalExpress_payment_status_refunded',
        ];
    }

    protected function fieldTypes(): array
    {
        return [
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
