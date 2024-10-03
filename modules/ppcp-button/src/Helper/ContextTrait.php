<?php
/**
 * Helper trait for context.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;

trait ContextTrait {
	/**
	 * Initializes context preconditions like is_cart() and is_checkout().
	 *
	 * @return void
	 */
	protected function init_context(): void {
		if ( ! apply_filters( 'woocommerce_paypal_payments_block_classic_compat', true ) ) {
			return;
		}

		/**
		 * Activate is_checkout() on woocommerce/classic-shortcode checkout blocks.
		 *
		 * @psalm-suppress MissingClosureParamType
		 */
		add_filter(
			'woocommerce_is_checkout',
			function ( $is_checkout ) {
				if ( $is_checkout ) {
					return $is_checkout;
				}

				if ( has_block( 'woocommerce/classic-shortcode' ) ) {
					$classic_block = $this->find_classic_shortcode_block();
					$type          = $classic_block['attrs']['shortcode'] ?? '';
					return $type === 'checkout';
				}

				return $is_checkout;
			}
		);

		// Activate is_cart() on woocommerce/classic-shortcode cart blocks.
		if ( ! is_cart() && is_callable( 'wc_maybe_define_constant' ) ) {
			if ( has_block( 'woocommerce/classic-shortcode' ) ) {
				$classic_block = $this->find_classic_shortcode_block();
				$type          = $classic_block['attrs']['shortcode'] ?? '';
				if ( $type !== 'checkout' ) { // There is no 'cart' type, the attribute is just missing.
					wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
				}
			}
		}
	}

	/**
	 * Returns the parsed data of the classic shortcode block, or null if missing on the current page.
	 * Slower than has_block( 'woocommerce/classic-shortcode' )
	 *
	 * @return array<string, mixed>|null
	 */
	protected function find_classic_shortcode_block(): ?array {
		$post = get_the_content();
		if ( ! $post ) {
			return null;
		}

		$blocks = parse_blocks( $post );
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'woocommerce/classic-shortcode' ) {
				return $block;
			}
		}

		return null;
	}

	/**
	 * Checks WC is_checkout() + WC checkout ajax requests.
	 */
	private function is_checkout(): bool {
		if ( is_checkout() ) {
			return true;
		}

		if ( $this->is_checkout_ajax() ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if performing the WC checkout ajax requests.
	 */
	private function is_checkout_ajax(): bool {
		/**
		 * The filter returning whether to detect WC checkout ajax requests.
		 */
		if ( ! apply_filters( 'ppcp_check_ajax_checkout', true ) ) {
			return false;
		}

		$wc_ajax = $this->wc_ajax_endpoint_name();
		return in_array( $wc_ajax, array( 'update_order_review' ), true );
	}

	/**
	 * Checks WC is_cart() + WC cart ajax requests.
	 */
	private function is_cart(): bool {
		if ( is_cart() ) {
			return true;
		}

		if ( $this->is_cart_ajax() ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if performing the WC cart ajax requests.
	 */
	private function is_cart_ajax(): bool {
		/**
		 * The filter returning whether to detect WC checkout ajax requests.
		 */
		if ( ! apply_filters( 'ppcp_check_ajax_cart', true ) ) {
			return false;
		}

		$wc_ajax = $this->wc_ajax_endpoint_name();
		return in_array( $wc_ajax, array( 'update_shipping_method' ), true );
	}

	/**
	 * Returns the current WC ajax endpoint name or an empty string if not in ajax.
	 */
	private function wc_ajax_endpoint_name(): string {
		// phpcs:ignore WordPress.Security
		$wc_ajax = $_GET['wc-ajax'] ?? '';
		if ( ! is_string( $wc_ajax ) ) {
			return '';
		}
		return $wc_ajax;
	}

	/**
	 * The current context.
	 *
	 * @return string
	 */
	protected function context(): string {
		// Default context.
		$context = 'mini-cart';

		switch ( true ) {
			case $this->is_cart_ajax():
				$context = 'cart';
				break;

			case $this->is_checkout_ajax() && ! $this->is_paypal_continuation():
				$context = 'checkout';
				break;

			case is_product() || wc_post_content_has_shortcode( 'product_page' ):
				// Do this check here instead of reordering outside conditions.
				// In order to have more control over the context.
				if ( $this->is_checkout() && ! $this->is_paypal_continuation() ) {
					$context = 'checkout';
				} else {
					$context = 'product';
				}
				break;

			// has_block may not work if called too early, such as during the block registration.
			case has_block( 'woocommerce/cart' ):
				$context = 'cart-block';
				break;

			case $this->is_cart():
				$context = 'cart';
				break;

			case is_checkout_pay_page():
				$context = 'pay-now';
				break;

			case has_block( 'woocommerce/checkout' ):
				$context = 'checkout-block';
				break;

			case $this->is_checkout() && ! $this->is_paypal_continuation():
				$context = 'checkout';
				break;

			case $this->is_add_payment_method_page():
				$context = 'add-payment-method';
				break;

			case $this->is_block_editor():
				$context = 'block-editor';
				break;
		}

		return apply_filters( 'woocommerce_paypal_payments_context', $context );
	}

	/**
	 * The current location.
	 *
	 * @return string
	 */
	protected function location(): string {
		$context = $this->context();
		if ( $context !== 'mini-cart' ) {
			return $context;
		}

		if ( is_shop() || is_product_category() ) {
			return 'shop';
		}

		if ( is_front_page() ) {
			return 'home';
		}

		return '';
	}

	/**
	 * Checks if PayPal payment was already initiated (on the product or cart pages).
	 *
	 * @return bool
	 */
	private function is_paypal_continuation(): bool {
		/**
		 * Cannot guarantee that initialized in all places where this trait is used,
		 * the Psalm checks seem to work weird and sometimes ignore missing property.
		 *
		 * @psalm-suppress RedundantPropertyInitializationCheck
		 */
		if ( ! isset( $this->session_handler ) ) {
			return false;
		}

		/**
		 * Property is already defined in trait consumers.
		 *
		 * @psalm-suppress UndefinedThisPropertyFetch
		 */
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}

		if ( ! $order->status()->is( OrderStatus::APPROVED )
			&& ! $order->status()->is( OrderStatus::COMPLETED )
		) {
			return false;
		}

		$source = $order->payment_source();
		if ( $source && $source->name() === 'card' ) {
			return false; // Ignore for DCC.
		}

		if ( 'card' === $this->session_handler->funding_source() ) {
			return false; // Ignore for card buttons.
		}

		return true;
	}

	/**
	 * Checks whether current page is Add payment method.
	 *
	 * @return bool
	 */
	private function is_add_payment_method_page(): bool {
		/**
		 * Needed for WordPress `query_vars`.
		 *
		 * @psalm-suppress InvalidGlobal
		 */
		global $wp;

		$page_id = wc_get_page_id( 'myaccount' );

		return $page_id && is_page( $page_id ) && isset( $wp->query_vars['add-payment-method'] );
	}

	/**
	 * Checks whether this user is changing the payment method for a subscription.
	 *
	 * @return bool
	 */
	private function is_subscription_change_payment_method_page(): bool {
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wcs_is_subscription( wc_clean( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		return false;
	}

	/**
	 * Checks if it is the block editor page.
	 */
	protected function is_block_editor(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && $screen->is_block_editor();
	}

	/**
	 * Checks if is WooCommerce Settings Payments tab screen (/wp-admin/admin.php?page=wc-settings&tab=checkout).
	 *
	 * @return bool
	 */
	protected function is_wc_settings_payments_tab(): bool {
		if ( ! is_admin() || isset( $_GET['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		$page = wc_clean( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = wc_clean( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		return $page === 'wc-settings' && $tab === 'checkout';
	}
}
