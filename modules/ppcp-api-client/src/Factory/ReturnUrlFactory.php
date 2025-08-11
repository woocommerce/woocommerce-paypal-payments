<?php
/**
 * Factory for determining the appropriate return URL based on context.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ReturnUrlFactory
 */
class ReturnUrlFactory {

	/**
	 * @throws RuntimeException When required data is missing for the context.
	 */
	public function from_context( string $context, array $request_data = array() ): string {
		switch ( $context ) {
			case 'cart':
			case 'cart-block':
			case 'mini-cart':
				return wc_get_cart_url();
			case 'product':
				if ( ! empty( $request_data['purchase_units'] ) && is_array( $request_data['purchase_units'] ) ) {
					$first_unit = reset( $request_data['purchase_units'] );
					if ( ! empty( $first_unit['items'] ) && is_array( $first_unit['items'] ) ) {
						$first_item = reset( $first_unit['items'] );
						if ( ! empty( $first_item['url'] ) ) {
							return $first_item['url'];
						}
					}
				}

				throw new RuntimeException( 'Product URL is required but not provided in the request data.' );
			case 'pay-now':
				if ( ! empty( $request_data['order_id'] ) ) {
					$order = wc_get_order( $request_data['order_id'] );
					if ( $order instanceof \WC_Order ) {
						return $order->get_checkout_payment_url();
					}
				}

				throw new RuntimeException( 'The order ID is invalid.' );
			default:
				return wc_get_checkout_url();
		}
	}
}
