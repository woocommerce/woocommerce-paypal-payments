<?php
/**
 * Identifies a product that cannot be bought through the PayPal buttons.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

trait ProductPaymentSupportTrait {

	/**
	 * Whether the product on view cannot be bought through the PayPal buttons.
	 */
	protected function is_unsupported_product(): bool {
		$product = wc_get_product();

		return $product instanceof WC_Product && ! $this->product_supports_payment( $product );
	}

	protected function product_supports_payment( WC_Product $product ): bool {
		$enable_button = ! $product->is_type( array( 'external', 'grouped' ) )
			&& $this->has_stock( $product )
			&& ! $this->is_subscription_switch( $product );

		/**
		 * Allows to filter if PayPal buttons/messages can be rendered for the given product.
		 *
		 * @param bool       $enable_button Whether the buttons/messages can be rendered.
		 * @param WC_Product $product       The product on view.
		 */
		return (bool) apply_filters(
			'woocommerce_paypal_payments_product_supports_payment_request_button',
			$enable_button,
			$product
		);
	}

	private function has_stock( WC_Product $product ): bool {
		if ( ! $product->is_type( 'variable' ) ) {
			return $product->is_in_stock();
		}

		assert( $product instanceof WC_Product_Variable );

		/** @var WC_Product_Variation[] $variations */
		$variations = $product->get_available_variations( 'objects' );

		foreach ( $variations as $variation ) {
			if ( $variation->is_in_stock() ) {
				return true;
			}
		}

		return false;
	}

	private function is_subscription_switch( WC_Product $product ): bool {
		$subscription_types = array( 'subscription', 'variable-subscription' );
		if ( ! $product->is_type( $subscription_types ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_GET['switch-subscription'] );
	}
}
