# WooCommerce Blueprints Integration

PayPal Payments integrates with WooCommerce Blueprints to let you export and import your PayPal payment configuration across WooCommerce sites.

## Overview

WooCommerce Blueprints (introduced in WooCommerce 9.9) allows you to capture your store's setup as a portable `.json` file that can be applied to other sites. PayPal Payments extends this feature so your PayPal configuration is included in the Blueprint. With this integration you can:

- Reuse a pre-configured PayPal setup across multiple stores
- Migrate PayPal settings between environments (development, staging, production)
- Maintain consistent payment configuration across a team

## Prerequisites

1. **WooCommerce 9.9 or later** is required.
2. The Blueprints feature must be enabled:
   - Navigate to **WooCommerce > Settings > Advanced > Features**
   - Enable the **Blueprint (beta)** checkbox
   - Select **Save changes**
3. PayPal Payments must be connected to a PayPal account.

Once enabled, a **WooCommerce Blueprint Export & Import** card appears in **PayPal Payments > Settings > Expert Settings**.

## Exporting a Blueprint

Exporting saves your current PayPal payment configuration into a `.json` file.

1. Go to **PayPal Payments > Settings > Expert Settings**
2. Find the **WooCommerce Blueprint Export & Import** card
3. Click **Export**
4. A file named `paypal-blueprint-{timestamp}.json` is downloaded

The exported Blueprint includes your PayPal payment configuration, gateway enable/disable states, button styling, and Pay Later messaging settings.

## Importing a Blueprint

Importing applies a previously exported configuration to a new or existing WooCommerce site.

1. Go to **WooCommerce > Settings > Advanced > Blueprints** (or click the **Import** button on the Expert Settings card)
2. Upload the Blueprint `.json` file
3. Click **Import**

After import, the plugin is configured according to the Blueprint and payments work as expected.

### Coming Soon Mode Restriction

WooCommerce only allows Blueprint imports when the store is in **Coming Soon** mode. This prevents accidental configuration changes on live sites.

If you need to import on a live site (e.g., for staging or testing), add the following to your `wp-config.php`:

```php
define( 'ALLOW_BLUEPRINT_IMPORT_IN_LIVE_MODE', true );
```

## Security

- Only administrators with `manage_woocommerce` and `manage_options` capabilities can export or import Blueprints.
- Imports only accept recognized PayPal Payments options; unrelated or unexpected data is ignored.
- WooCommerce enforces strict JSON schema validation and blocks updates to sensitive WordPress options (e.g., `siteurl`, `home`).
- All Blueprint operations are logged under **WooCommerce > Status > Logs** with the source `wc-blueprint`.

Review Blueprint files before importing, especially if they come from a third party. Store exported files securely as they may contain sensitive information.
