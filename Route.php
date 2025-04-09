<?php
use Illuminate\Support\Facades\Route;

$config = file_get_contents(__DIR__.'/gp247.json');
$config = json_decode($config, true);

if(gp247_extension_check_active($config['configGroup'], $config['configKey'])) {


    Route::group(
    [
        'middleware' => GP247_FRONT_MIDDLEWARE,
        'prefix'    => 'plugin/paypal-express',
        'namespace' => 'App\GP247\Plugins\PaypalExpress\Controllers',
    ],
    function () {
        Route::get('index', 'FrontController@index')
        ->name('paypal-express.index');
        Route::post('webhook', 'FrontController@handleWebhook')
        ->name('paypal-express.webhook');
        Route::get('create-subscription', 'FrontController@createSubscription')
            ->name('paypal-express.create_subscription');
        Route::get('capture-payment', 'FrontController@capturePayment')
            ->name('paypal-express.capture_payment');
        Route::get('cancel-payment', 'FrontController@cancelPayment')
            ->name('paypal-express.cancel_payment');
    }
);

    Route::group(
        [
            'prefix' => GP247_ADMIN_PREFIX.'/paypal-express',
            'middleware' => GP247_ADMIN_MIDDLEWARE,
            'namespace' => '\App\GP247\Plugins\PaypalExpress\Admin',
        ], 
        function () {
            Route::get('/', 'AdminController@index')
            ->name('admin_paypal-express.index');
        }
    );
}