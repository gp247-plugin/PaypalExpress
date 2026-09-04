<?php
/**
 * Provides everything needed for the Extension
 */

 $config = file_get_contents(__DIR__.'/gp247.json');
 $config = json_decode($config, true);
 $extensionPath = $config['configGroup'].'/'.$config['configKey'];
 
 $this->loadTranslationsFrom(__DIR__.'/Lang', $extensionPath);
 
 if (gp247_extension_check_active($config['configGroup'], $config['configKey'])) {
     
     $this->loadViewsFrom(__DIR__.'/Views', $extensionPath);
     
     if (file_exists(__DIR__.'/config.php')) {
         $this->mergeConfigFrom(__DIR__.'/config.php', $extensionPath);
     }
 
     if (file_exists(__DIR__.'/function.php')) {
         require_once __DIR__.'/function.php';
     }

     // storeScope = "platform" (gp247.json): the PayPal config screen is owner-only.
     // We deliberately DO NOT append this plugin's admin segment to
     // gp247-config.admin.store_scoped_segments, so the MultiStore Pro fence keeps the
     // screen GLOBAL and blocks store-admins/vendors — a vendor must never be able to
     // change the store's payment credentials. The root admin still configures per store
     // via the ConfigForm picker (ADR paypal-express_per-store-credentials). This is the
     // intended contrast with a storeScope=store plugin (e.g. ShippingStandard), which
     // DOES append its segment.
 }

// Add CSRF exceptions for PayPal webhook routes
$this->app->resolving(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, function ($middleware) {
    $middleware->except([
        'plugin/paypal/webhook',
    ]);
});