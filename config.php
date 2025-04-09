<?php
return [
    'sandbox' => env('PAYPAL_SANDBOX', true),
    'client_id_sandbox' => env('PAYPAL_CLIENT_ID_SANDBOX', ''),
    'client_secret_sandbox' => env('PAYPAL_CLIENT_SECRET_SANDBOX', ''),
    'client_id_live' => env('PAYPAL_CLIENT_ID_LIVE', ''),
    'client_secret_live' => env('PAYPAL_CLIENT_SECRET_LIVE', ''),
    'return_url' => env('PAYPAL_RETURN_URL', 'https://127.0.0.1/plugin/paypal-express/capture-payment'),
    'cancel_url' => env('PAYPAL_CANCEL_URL', 'https://127.0.0.1/plugin/paypal-express/cancel-payment'),
    'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
];