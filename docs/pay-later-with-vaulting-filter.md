# Pay Later with vaulting filter (deprecated)

## Overview

Pay Later and vaulting ("Save PayPal and Venmo") are no longer mutually exclusive. Pay Later buttons, messaging, the payment method toggle, and the Pay Later blocks stay available while vaulting is active.

## Deprecated filter

`woocommerce_paypal_payments_pay_later_with_vaulting` is deprecated since 4.1.4. Its return value is ignored, and it can no longer restore the old mutually exclusive behavior. See `SettingsProvider::pay_later_with_vaulting_enabled()` in `modules/ppcp-settings/src/Data/SettingsProvider.php`.

## Deprecated methods

These `SettingsProvider` methods are kept only for backward compatibility. The plugin no longer calls them:

- `pay_later_with_vaulting_enabled()`
- `pay_later_disabled_by_vaulting()`

## Notes

- PayPal still decides at runtime whether to offer Pay Later to a buyer.
- To hide storefront messaging, use `woocommerce_paypal_payments_should_render_pay_later_messaging`.
