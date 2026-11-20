# Refund money filter

## Overview

PayPal refunds a capture only in the currency of that capture. By default, `RefundProcessor::refund()` sends the refund in the currency of the WooCommerce order.

A multi-currency plugin can create the PayPal order in another currency than the WooCommerce order. For example, the WooCommerce order is AED 400.00, and PayPal captures USD 100.00. A refund in AED then fails with `REFUND_CAPTURE_CURRENCY_MISMATCH`. The filter lets the plugin send the refund in the currency of the capture.

## Filter

### `woocommerce_paypal_payments_refund_money`

Filters the `Money` of a refund before `RefundProcessor::refund()` sends it to PayPal.

| Argument    | Type       | Description                                          |
|-------------|------------|------------------------------------------------------|
| `$money`    | `Money`    | The refund, in the WooCommerce order currency.       |
| `$wc_order` | `WC_Order` | The WooCommerce order.                               |
| `$capture`  | `Capture`  | The PayPal capture that is refunded.                 |
| `$order`    | `Order`    | The PayPal order, with the refunds already made.     |

Return a `Money` in the currency of the capture. PayPal Payments ignores any other return value and sends the unfiltered refund. It does not check the returned amount against the capture: PayPal rejects a refund in another currency, or above what the capture has left.

## Example: refund in proportion to the capture

```php
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;

add_filter(
	'woocommerce_paypal_payments_refund_money',
	function ( $money, $wc_order, $capture ) {
		$captured = $capture->amount();
		if ( $captured->currency_code() === $money->currency_code() ) {
			return $money;
		}

		$share = $money->value() / (float) $wc_order->get_total();

		return new Money( round( $captured->value() * $share, 2 ), $captured->currency_code() );
	},
	10,
	3
);
```

For a series of partial refunds, read the refunds already made on the capture from `$order`, so the rounded parts never exceed the capture.
