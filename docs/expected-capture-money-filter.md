# Expected capture money filter

## Overview

A capture webhook marks a WooCommerce order as paid only when the capture covers the expected money. The check is `is_wc_order_total_captured()` in `RequestHandlerTrait`. `PaymentCaptureCompleted` and `CheckoutOrderCompleted` use it. By default, the expected money is the WooCommerce order total, in the order currency.

A multi-currency plugin can create the PayPal order in another currency than the WooCommerce order. For example, the WooCommerce order is AED 400.00, and the plugin sends USD 100.00 to PayPal. A capture of USD 100.00 never matches AED 400.00, so a capture that completes later leaves the order on hold. The filter lets the plugin return the money it sent to PayPal.

## Filter

### `woocommerce_paypal_payments_expected_capture_money`

Filters the `Money` a capture must cover before the WooCommerce order is marked as paid.

| Argument    | Type       | Description                          |
|-------------|------------|--------------------------------------|
| `$total`    | `Money`    | The WooCommerce order total.         |
| `$wc_order` | `WC_Order` | The WooCommerce order.               |
| `$captured` | `Money`    | The captured money from the webhook. |

Return a `Money`. PayPal Payments ignores any other return value and uses the order total. The capture must still be in the currency of the returned money, and at least its value.

## Example: expect the amount sent to PayPal

The plugin saves the amount when it creates the PayPal order, for example in `ppcp_create_order_request_body_data`, and returns it here.

```php
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;

add_filter(
	'woocommerce_paypal_payments_expected_capture_money',
	function ( $total, $wc_order ) {
		$sent = $wc_order->get_meta( '_my_plugin_paypal_amount' );
		if ( ! is_array( $sent ) ) {
			return $total;
		}

		return new Money( (float) $sent['value'], (string) $sent['currency_code'] );
	},
	10,
	2
);
```
