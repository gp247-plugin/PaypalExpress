# PaypalExpress Plugin

> 🌐 **English** · [Tiếng Việt](readme_vi.md)

Accept **PayPal** payments in your GP247/Shop store. Customers pay for their orders directly with their PayPal account — fast, secure, and without ever leaving your site.

---

## At a glance

| | |
| --- | --- |
| **Plugin** | PaypalExpress |
| **Version** | 3.1 |
| **Developer** | GP247 |
| **Requires** | GP247 Core **3.0.3+** (per-store config + at-rest secret encryption) · package `gp247/shop` |

## What it does

- 💳 **Pay with PayPal** — customers check out with their PayPal account without leaving your site.
- 🧪 **Sandbox & Live** — switch between PayPal's testing and production environments with one toggle.
- 🔔 **Webhooks** — order status updates automatically from PayPal notifications.
- 🔒 **Secure** — PayPal webhook signatures are verified, and your client secrets are **encrypted at rest**.
- 💱 **Multi-currency** — works with GP247/Shop currencies (just check the currency is supported by PayPal).

---

## Installation

Pick whichever is easier for you.

**From the Admin panel**
1. Log in to your GP247 admin.
2. Go to **Extensions / Plugins**.
3. Find **PaypalExpress** and click **Install**.
4. Follow the on-screen steps.

**From a ZIP file**
1. Download the plugin ZIP from the official source.
2. In the admin, go to **Extensions / Plugins → Import / Upload**.
3. Choose the ZIP and upload it.

Then **activate** the plugin in the plugin manager.

---

## Configuration

Since **version 3.1**, everything is set up in the admin — no `.env` editing needed.

Open **Admin → Plugins → Paypal Express** and fill in these fields (**per store**):

| Field | What to enter |
| --- | --- |
| **Sandbox mode** | On = PayPal test environment · Off = live |
| **Client ID / Secret (Sandbox)** | Your sandbox credentials |
| **Client ID / Secret (Live)** | Your live credentials |
| **Webhook ID** | The webhook ID from your PayPal Developer account (see [Webhooks](#webhooks) below) |

Good to know:

- 🔒 **Client secrets are encrypted at rest** (`enc:v2:…`) — never stored in plain text.
- 🏬 **Per store** — a multi-store owner sets a PayPal account for each store; on a marketplace the platform owner sets one account and stores inherit it. Only the site/marketplace owner can open this screen.
- 🔗 **Return / cancel URLs are automatic** — the plugin builds them from the store's own domain, so there is nothing to configure.

> **Upgrading from an older `.env` setup?** Older versions kept credentials in `.env`. On upgrade to 3.1 the plugin **imports them once** into the database (secrets encrypted), then reads only the database — `.env` is no longer used at runtime. Your `.env` file is left untouched and can be removed afterwards.
>
> *If your site runs `php artisan config:cache`, the automatic import is skipped — just re-enter the values in the admin screen.*

**Legacy `.env` variables** (only for the one-time migration above):

```
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID_SANDBOX=your_sandbox_client_id
PAYPAL_CLIENT_SECRET_SANDBOX=your_sandbox_client_secret
PAYPAL_CLIENT_ID_LIVE=your_live_client_id
PAYPAL_CLIENT_SECRET_LIVE=your_live_client_secret
PAYPAL_WEBHOOK_ID=your_webhook_id
```

---

## Currency support

GP247 supports many currencies, but **PayPal only accepts some of them**:

- Check your currency against the [PayPal Supported Currencies](https://developer.paypal.com/docs/api/reference/currency-codes/) list first.
- If a customer tries to pay in an unsupported currency, they see an error message.
- Safest choices: **USD, EUR, GBP, CAD, AUD**.

---

## How a payment works

1. The customer adds products to the cart and checks out.
2. GP247 creates the order and sends the customer to PayPal.
3. The customer logs in to PayPal and confirms.
4. PayPal sends the customer back to your site.
5. GP247 verifies the transaction and updates the order status.
6. The customer sees the payment confirmation.

---

## Webhooks

The plugin listens for PayPal notifications at:

```
https://your-domain.com/plugin/paypal-express/webhook
```

Register this URL in your **PayPal Developer** account, then paste the **Webhook ID** into the plugin's admin screen (per store).

---

## Changelog

### Version 3.1
- PayPal credentials (client id/secret sandbox+live, webhook id, sandbox toggle) moved from `.env` into the admin screen, **per store**, with client secrets **encrypted at rest** (`enc:v2:…`). `storeScope: store` — the root admin can set a PayPal account per store; the plugin stays out of `store_scoped_segments`, so only the site/marketplace owner (never a vendor) can open the screen.
- **The database is the single runtime source of configuration — `.env` is no longer read.** A legacy `.env` is **imported once** on upgrade (secrets encrypted), then the database is used exclusively. `return_url`/`cancel_url` are no longer configured by hand; they are derived from the plugin route and the store's domain.
- Requires GP247 Core 3.0.3+.

### Version 2.0
- Admin configuration screen rebuilt on TailAdmin/Livewire (requires GP247 Core 2.0); order/payment status for success and refund events are now edited as dropdowns, backed by the same `admin_config` rows as before, so already-configured values carry over on upgrade.
- Fixed a pre-existing bug where the "Paypal Express" entry under Payment method in the admin sidebar could be duplicated on install and was never removed on uninstall (wrong menu URI).

### Version 1.0.0
- Initial release.

---

<sub>Developed by **GP247** · distributed under its respective license.</sub>
