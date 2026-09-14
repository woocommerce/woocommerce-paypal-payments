# Pay Later with Vaulting Filter

## Overview
Pay Later and Vaulting ("Save PayPal and Venmo") can be active at the same time whenever the
merchant is eligible for Pay Later, which is the same eligibility the plugin applies to every
other Pay Later feature (the merchant country must be one of the supported Pay Later countries).

For merchants who are **not** eligible for Pay Later, the previous behaviour still applies. When
vaulting is active:

- The **Payment Methods** tab locks the "Pay Later" toggle ("This payment method requires
  Save PayPal and Venmo to be disabled").
- Pay Later **messaging** is disabled everywhere.

## Filter

### `woocommerce_paypal_payments_pay_later_with_vaulting`
Controls whether Pay Later features may run while vaulting is active. Defaults to the merchant's
Pay Later eligibility.

Restore the mutually exclusive behaviour:

```php
add_filter( 'woocommerce_paypal_payments_pay_later_with_vaulting', '__return_false' );
```

Allow the combination for a merchant the plugin does not consider eligible:

```php
add_filter( 'woocommerce_paypal_payments_pay_later_with_vaulting', '__return_true' );
```

## Notes
- Forcing the combination on an account that PayPal has not approved for Pay Later + Vaulting may
  result in Pay Later still not being offered by PayPal at runtime.
- The supported Pay Later countries are filterable in their own right, via
  `woocommerce_paypal_payments_supported_paylater_countries`.
- **Use this filter, not `woocommerce_paypal_payments_should_render_pay_later_messaging`.**
  The latter only toggles storefront messaging rendering and, on its own, cannot re-enable
  Pay Later when vaulting is active — the plugin still forces messaging off under vaulting.
  `woocommerce_paypal_payments_pay_later_with_vaulting` is the intended override and unlocks
  the configuration UI as well.
