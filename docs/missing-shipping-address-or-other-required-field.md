# Dealing with MISSING_SHIPPING_ADDRESS error or similar (MISSING_%field_name%)
How to deal with errors like `MISSING_%field_name%`, for example `MISSING_SHIPPING_ADDRESS`.

## Overview
These errors happen when some of required fields is not present on classic checkout.
Typically, this is done by the plugin changing checkout fields (e.g. Flexible Checkout Fields).
The error originates in PayPal, and is displayed on classic checkout after the unsuccessful payment
try.

## When this error occurs
- Using WooCommerce classic checkout
- Third-party plugins modify checkout fields (e.g., Flexible Checkout Fields)
- Selling physical products that require shipping
- Required shipping fields are missing (usually removed from checkout)

## Anatomy of the problem
PayPal may be instructed to get shipping address from merchant, from buyer or to ignore it completely (see [create orders](https://developer.paypal.com/docs/api/orders/v1/#orders_create)).
For physical products, and when matching some other conditions, this plugin makes PayPal to expect
shipping details from merchant. But if merchant modified checkout fields and removed required
ones like Address line 1, then PayPal rejects transaction.

To solve this problem, we need to tell PayPal to ignore shipping address completely. But this is only
half of the problem, because the plugin still sends incomplete shipping fields, causing another PayPal
error. The second part of the solution is to prevent sending shipping fields at all.
On top of this, it should be only applied to classic checkout because other checkouts doesn't have
this problem, even more - this solution may create other problems when applied to anything except
for classic checkout.

## The solution
It fits in a few lines of code.

```php
/**
 * Fix for MISSING_SHIPPING_ADDRESS and similar MISSING_*field_name* errors
 * Only applies to classic checkout to avoid conflicts with other checkout types
 */
add_action(
	'wc_ajax_checkout',
	function (): void {
		// Disable PayPal expectation for shipping fields.
		add_filter( 'woocommerce_paypal_payments_shipping_preference', fn() => 'NO_SHIPPING' );
		// Prevent sending shipping fields from order and cart.
		add_filter( 'woocommerce_paypal_payments_shipping_needed', '__return_false' );
	},
	0 // High priority to ensure it runs early
);
```
This code can be placed as-is in a super simple plugin, consisting of a standard plugin header and
this code.

## Verifying the fix
1. Complete a test purchase using classic checkout, making sure that the PayPal transaction completes without MISSING_* errors
2. Make sure other checkout methods (blocks, express. pay-for-order) still work normally
