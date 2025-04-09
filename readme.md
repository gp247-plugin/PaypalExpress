# PaypalExpress Plugin

## Overview

PaypalExpress is a plugin that provides PayPal payment functionality for GP247/Shop. This plugin allows customers to pay for their orders directly using their PayPal accounts, offering a fast and secure payment experience.

## Basic Information

- **Plugin Name**: PaypalExpress
- **Version**: 1.0.0
- **Developer**: GP247
- **Support Email**: support@gp247.net
- **Link**: https://github.com/gp247net/PaypalExpress
- **System Requirements**: 
  - GP247 Core version 1.1 or higher
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

To use the plugin, you need to configure the following information in your `.env` file:

```
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID_SANDBOX=your_sandbox_client_id
PAYPAL_CLIENT_SECRET_SANDBOX=your_sandbox_client_secret
PAYPAL_CLIENT_ID_LIVE=your_live_client_id
PAYPAL_CLIENT_SECRET_LIVE=your_live_client_secret
PAYPAL_RETURN_URL=https://your-domain.com/plugin/paypal-express/capture-payment
PAYPAL_CANCEL_URL=https://your-domain.com/plugin/paypal-express/cancel-payment
PAYPAL_WEBHOOK_ID=your_webhook_id
```

Where:
- `PAYPAL_SANDBOX`: Set to `true` to use the sandbox environment, `false` to use the live environment.
- `PAYPAL_CLIENT_ID_SANDBOX` and `PAYPAL_CLIENT_SECRET_SANDBOX`: Authentication credentials for the sandbox environment.
- `PAYPAL_CLIENT_ID_LIVE` and `PAYPAL_CLIENT_SECRET_LIVE`: Authentication credentials for the live environment.
- `PAYPAL_RETURN_URL`: URL to which PayPal will redirect after successful payment.
- `PAYPAL_CANCEL_URL`: URL to which PayPal will redirect if the customer cancels the payment.
- `PAYPAL_WEBHOOK_ID`: Webhook ID created in your PayPal Developer account.

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

You need to register this webhook in your PayPal Developer account and update the `PAYPAL_WEBHOOK_ID` in your `.env` file.

## Support and Contact

If you need support or have questions about the PaypalExpress plugin, please contact:

- Email: support@gp247.net
- GitHub: https://github.com/gp247net/PaypalExpress

## License

The PaypalExpress plugin is developed by GP247 and distributed under the appropriate license.
