<?php
/**
 * Helper trait for context.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

trait ContextTrait {

	/**
	 * The current context.
	 *
	 * @return string
	 */
	protected function context(): string {
		if ( is_product() || wc_post_content_has_shortcode( 'product_page' ) ) {
			return 'product';
		}

		// has_block may not work if called too early, such as during the block registration.
		if ( has_block( 'woocommerce/cart' ) ) {
			return 'cart-block';
		}

		if ( is_cart() ) {
			return 'cart';
		}

		if ( is_checkout_pay_page() ) {
			return 'pay-now';
		}

		if ( has_block( 'woocommerce/checkout' ) ) {
			return 'checkout-block';
		}

		if ( ( is_checkout() ) && ! $this->is_paypal_continuation() ) {
			return 'checkout';
		}

		return 'mini-cart';
	}

	/**
	 * Checks if PayPal payment was already initiated (on the product or cart pages).
	 *
	 * @return bool
	 */
	private function is_paypal_continuation(): bool {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}

		$source = $order->payment_source();
		if ( $source && $source->card() ) {
			return false; // Ignore for DCC.
		}

		if ( 'card' === $this->session_handler->funding_source() ) {
			return false; // Ignore for card buttons.
		}

		return true;
	}
}
