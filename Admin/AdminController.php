<?php
#App\GP247\Plugins\PaypalExpress\Admin\AdminController.php

namespace App\GP247\Plugins\PaypalExpress\Admin;

use GP247\Core\Controllers\RootAdminController;
use App\GP247\Plugins\PaypalExpress\AppConfig;

class AdminController extends RootAdminController
{
    public $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = new AppConfig;
    }
    public function index()
    {
        $orderStatusSuccess = \GP247\Shop\Models\ShopOrderStatus::getIdAll();
        $paymentStatusSuccess = \GP247\Shop\Models\ShopPaymentStatus::getIdAll();
        return view($this->plugin->appPath.'::Admin',
            [
                'storeId' => session('adminStoreId'),
                'orderStatusSuccess' => $orderStatusSuccess,
                'paymentStatusSuccess' => $paymentStatusSuccess,
                'urlUpdateConfig' => gp247_route_admin('admin_config.update'),
                'urlUpdateConfigGlobal' => gp247_route_admin('admin_config_global.update'),
            ]
        );
    }
}
