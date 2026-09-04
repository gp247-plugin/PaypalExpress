# PaypalExpress Plugin

## Overview

PaypalExpress is a plugin that provides PayPal payment functionality for GP247/Shop. This plugin allows customers to pay for their orders directly using their PayPal accounts, offering a fast and secure payment experience.

## Basic Information

- **Plugin Name**: PaypalExpress
- **Version**: 3.1
- **Developer**: GP247
- **Support Email**: support@gp247.net
- **Link**: https://github.com/gp247net/PaypalExpress
- **System Requirements**: 
  - GP247 Core version 3.0.3 or higher (needs per-store config + at-rest secret encryption)
  - Package gp247/shop

## Key Features

1. **Direct PayPal Payment**: Allows customers to pay for orders using their PayPal accounts without leaving your website.

2. **Sandbox and Live Environment Support**: Configurable to use either PayPal's sandbox (testing) or live environment.

3. **Webhook Processing**: Automatically updates order status based on notifications from PayPal via webhooks.

4. **High Security**: Integrates PayPal's webhook signature verification to ensure transaction security.

5. **Multi-Currency Support**: Integrates with GP247/Shop's currency system. However, you need to verify that the currency you use is supported by PayPal.

## Installation and Configuration

### Installation

There are two ways to install the PaypalExpress plugin:

#### Method 1: Automatic Installation via Admin Panel
1. Log in to your GP247 admin panel.
2. Navigate to the "Extensions" or "Plugins" section.
3. Find "PaypalExpress" in the list of available plugins.
4. Click the "Install" button next to it.
5. Follow the on-screen instructions to complete the installation.

#### Method 2: Manual Installation via ZIP File
1. Download the PaypalExpress plugin ZIP file from the official source.
2. Log in to your GP247 admin panel.
3. Navigate to the "Extensions" or "Plugins" section.
4. Click on the "Import" or "Upload" button.
5. Select the downloaded ZIP file and click "Upload" or "Import".
6. Follow the on-screen instructions to complete the installation.

After installation, activate the plugin in the plugin management section.

### Configuration

**From version 3.1**, PayPal credentials are configured **entirely** in **Admin -> Plugins -> Paypal Express**, **per store**, and the client secrets are **encrypted at rest** (`enc:v2:...`). **The database is the single runtime source of configuration — `.env` is no longer read.** On a multi-store site the site owner sets a PayPal account per store; on a marketplace the platform owner sets one account and stores inherit it. Only the site/marketplace owner can open this screen (store-admins/vendors are blocked). The fields: Sandbox mode, Client ID/Secret (Sandbox), Client ID/Secret (Live), Webhook ID.

The post-payment redirect URL (`return_url`) and cancel URL (`cancel_url`) **need no configuration** — the plugin derives them from its route and the current store's domain.

> **Legacy `.env` (no longer read at runtime):** the `PAYPAL_*` variables below are **not consulted at runtime**. When upgrading to 3.1, if a legacy site still has them in `.env`, the plugin **imports them once** into the database (client secrets encrypted) so nothing is lost, then uses the database only. `.env` is left intact but can be removed afterwards. (A site running `php artisan config:cache` cannot auto-import — re-enter the values directly in the admin screen.)

```
# Legacy — one-time migration on upgrade only, not used at runtime
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID_SANDBOX=your_sandbox_client_id
PAYPAL_CLIENT_SECRET_SANDBOX=your_sandbox_client_secret
PAYPAL_CLIENT_ID_LIVE=your_live_client_id
PAYPAL_CLIENT_SECRET_LIVE=your_live_client_secret
PAYPAL_WEBHOOK_ID=your_webhook_id
```

### Currency Support

While GP247 supports multiple currencies, PayPal has specific currency requirements:

- PayPal supports a limited set of currencies for transactions.
- Before using a specific currency, verify that it is supported by PayPal by checking the [PayPal Supported Currencies](https://developer.paypal.com/docs/api/reference/currency-codes/) documentation.
- If you attempt to process a payment with an unsupported currency, the plugin will display an error message to the customer.
- For optimal compatibility, consider using major currencies such as USD, EUR, GBP, CAD, or AUD.

## Payment Process

1. Customer adds products to cart and proceeds to checkout.
2. The system creates an order and redirects the customer to the PayPal payment page.
3. Customer logs in to their PayPal account and confirms the payment.
4. PayPal redirects the customer back to your website.
5. The system verifies the transaction and updates the order status.
6. Customer receives payment confirmation.

## Webhook Processing

The plugin integrates PayPal webhook processing to automatically update order statuses. The webhook will be sent to:

```
https://your-domain.com/plugin/paypal-express/webhook
```

You need to register this webhook in your PayPal Developer account and enter the **Webhook ID** in the plugin's admin configuration screen (per store).

## Support and Contact

If you need support or have questions about the PaypalExpress plugin, please contact:

- Email: support@gp247.net
- GitHub: https://github.com/gp247net/PaypalExpress

## License

The PaypalExpress plugin is developed by GP247 and distributed under the appropriate license.

## Changelog

### Version 3.1
- PayPal credentials (client id/secret sandbox+live, webhook id, sandbox toggle) moved from `.env` into the admin config screen, **per store**, with client secrets **encrypted at rest** (`enc:v2:...`). `storeScope: platform` — only the site/marketplace owner configures; the root admin can set a PayPal account per store.
- **The database is the single runtime source of configuration — `.env` is no longer read.** A legacy site's `.env` is **imported once** on upgrade (client secrets encrypted), then the database is used exclusively. `return_url`/`cancel_url` are no longer configured by hand; they are derived from the plugin route and the store's domain.
- Requires GP247 Core 3.0.3+.

### Version 2.0
- Admin configuration screen rebuilt on TailAdmin/Livewire (requires GP247 Core 2.0); order/payment status for success and refund events are now edited as dropdowns, backed by the same `admin_config` rows as before, so already-configured values carry over on upgrade
- Fixed a pre-existing bug where the "Paypal Express" entry under Payment method in the admin sidebar could be duplicated on install and was never removed on uninstall (wrong menu URI)

### Version 1.0.0
- Initial release
