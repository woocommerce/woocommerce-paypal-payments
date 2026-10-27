<?php
/**
 * Identifies a product that cannot be bought through the PayPal buttons.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare(strict_types=1);

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
		$in_stock = $product->is_in_stock();

		if ( $product->is_type( 'variable' ) ) {
			assert( $product instanceof WC_Product_Variable );

			/** @var WC_Product_Variation[] $variations */
			$variations = $product->get_available_variations( 'objects' );
			$in_stock   = $this->has_in_stock_variation( $variations );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$enable_button = ! $product->is_type( array( 'external', 'grouped' ) ) && $in_stock &&
			! ( ( $product->is_type( 'subscription' ) || $product->is_type( 'variable-subscription' ) ) && ! empty( $_GET['switch-subscription'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

	/**
	 * @param WC_Product_Variation[] $variations
	 */
	protected function has_in_stock_variation( array $variations ): bool {
		foreach ( $variations as $variation ) {
			if ( $variation->is_in_stock() ) {
				return true;
			}
		}

		return false;
	}
}
