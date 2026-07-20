# Pay Later with Vaulting Filter

## Overview
By default the plugin treats **Pay Later** and **Vaulting** ("Save PayPal and Venmo") as
mutually exclusive. When vaulting is active:

- The **Payment Methods** tab locks the "Pay Later" toggle ("This payment method requires
  Save PayPal and Venmo to be disabled").
- Pay Later **messaging** is disabled everywhere.

Merchants that PayPal has **whitelisted** to offer Pay Later alongside Vaulting
can lift this restriction with a filter.

## Filter

### `woocommerce_paypal_payments_pay_later_with_vaulting`
Controls whether Pay Later features may run while vaulting is active. Defaults to `false`
(Pay Later disabled if vaulting is active). Return `true` to bypass the restriction.

```php
add_filter( 'woocommerce_paypal_payments_pay_later_with_vaulting', '__return_true' );
```

## Notes
- Intended for PayPal-whitelisted accounts. Enabling it on an account that is not approved
  for Pay Later + Vaulting may result in Pay Later still not being offered by PayPal at runtime.
- **Use this filter, not `woocommerce_paypal_payments_should_render_pay_later_messaging`.**
  The latter only toggles storefront messaging rendering and, on its own, cannot re-enable
  Pay Later when vaulting is active — the plugin still forces messaging off under vaulting.
  __`woocommerce_paypal_payments_pay_later_with_vaulting` is the intended override and unlocks
  the configuration UI as well.
