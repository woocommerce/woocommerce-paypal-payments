# Extra Discount Filters

## Overview

Some plugins apply discounts by calling `WC_Cart::set_total()` or `WC_Order::set_total()` directly,
bypassing WooCommerce's standard coupon and fee mechanisms. Because `AmountFactory` derives the
PayPal order amount from the standard breakdown getters (`get_subtotal()`, `get_discount_total()`,
etc.), such discounts are invisible to it and the customer would be charged the full amount on the
PayPal payment page.

Three filters allow third-party plugins to surface these amounts so they are correctly reflected in
the PayPal order.

## Filters

### `woocommerce_paypal_payments_cart_extra_discount`

Contributes an extra discount amount when a PayPal order is created from the WC cart
(`AmountFactory::from_wc_cart()`). The value is added to the `discount` field of the PayPal
amount breakdown.

```php
add_filter(
    'woocommerce_paypal_payments_cart_extra_discount',
    function ( float $discount, WC_Cart $cart ): float {
        // Return the additional discount amount that was applied to the cart
        // outside of WooCommerce's standard coupon/fee system.
        return $discount + my_plugin_get_cart_discount( $cart );
    },
    10,
    2
);
```

### `woocommerce_paypal_payments_order_extra_discount`

Contributes an extra discount amount when a PayPal order is created from a WC order
(`AmountFactory::from_wc_order()`).

```php
add_filter(
    'woocommerce_paypal_payments_order_extra_discount',
    function ( float $discount, WC_Order $order ): float {
        // Return the additional discount amount stored on the order.
        return $discount + my_plugin_get_order_discount( $order );
    },
    10,
    2
);
```

### `woocommerce_paypal_payments_store_api_cart_extra_discount`

Same as `woocommerce_paypal_payments_cart_extra_discount` but for the Store API path
(`AmountFactory::from_store_api_cart()`), used during the PayPal express button shipping callback.
The second argument is a `CartTotals` object instead of `WC_Cart`. Unlike the other filters,
the value must be in the currency's
**minor unit** (e.g. cents for USD) because the Store API works entirely in minor units.

```php
add_filter(
    'woocommerce_paypal_payments_store_api_cart_extra_discount',
    function ( int $discount, CartTotals $cart_totals ): int {
        // Return cents (minor units), not dollars.
        return $discount + my_plugin_get_cart_discount_cents( $cart_totals );
    },
    10,
    2
);
```

## Notes

- Each filter receives the currently accumulated extra discount as its first argument and must
  return it unchanged if your plugin has nothing to contribute.
- The cart and order filters return a `float` in the currency's major unit (e.g. dollars).
  The Store API filter returns an `int` in minor units (e.g. cents for USD).
- Negative values are clamped to `0` by the factory.
- The WooCommerce Gift Cards plugin (`woocommerce-gift-cards`) is handled automatically via
  `WcGiftCardsCompat` for the classic cart/checkout, express button shipping callback, and order
  paths. No manual
  integration is needed for that plugin.
