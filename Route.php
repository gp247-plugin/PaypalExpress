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
        Route::get('create-subscription', 'FrontController@createSubscription')
            ->name('paypal-express.create_subscription');
        Route::get('capture-payment', 'FrontController@capturePayment')
            ->name('paypal-express.capture_payment');
        Route::get('cancel-payment', 'FrontController@cancelPayment')
            ->name('paypal-express.cancel_payment');
        // Back from approving a core payment request (pay link): capture, record
        // idempotently, then back to the link's page.
        Route::get('payment-request/return', 'FrontController@paymentRequestReturn')
            ->middleware('throttle:30,1')
            ->name('paypal-express.payment_request.return');
    }
);

    // WHY a bare route: the webhook is a machine-to-machine call from PayPal. Inside the
    // storefront group it would need a CSRF token it cannot have (419), could be answered
    // with a maintenance page (check.active exits with 200, so PayPal would count the
    // event as delivered and never retry) or a redirect, and would open a session for
    // nothing. The store is already resolved from the domain at boot, which is all the
    // webhook needs; authenticity comes from PayPal's signature verification. The path is
    // unchanged, so a Webhook ID registered on an older version keeps working. Throttled
    // generously: signature checks are cheap for us but each one costs a PayPal API call,
    // so an unsigned flood should be cut off before it reaches PayPal.
    Route::post('plugin/paypal-express/webhook', [\App\GP247\Plugins\PaypalExpress\Controllers\FrontController::class, 'handleWebhook'])
        ->middleware('throttle:600,1')
        ->name('paypal-express.webhook');

    Route::group(
        [
            'prefix' => GP247_ADMIN_PREFIX.'/paypal-express',
            'middleware' => GP247_ADMIN_MIDDLEWARE,
        ],
        function () {
            Route::get('/', \App\GP247\Plugins\PaypalExpress\Livewire\AdminLivewire::class)
            ->name('admin_paypal-express.index');
        }
    );
}
