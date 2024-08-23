<?php
/**
 * Helper to detect what cart and checkout configuration is being used.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class CartCheckoutDetector
 */
class CartCheckoutDetector {

	/**
	 * Returns a list of Elementor widgets if they exist for a specific page.
	 *
	 * @param int $page_id The ID of the page.
	 *
	 * @return array List of widget types if any exist, otherwise an empty array.
	 */
	private static function get_elementor_widgets( $page_id ): array {
		$elementor_data = get_post_meta( $page_id, '_elementor_data' );

		if ( isset( $elementor_data[0] ) ) {
			// Parse the Elementor json and find all widgets for a specific page.
			$reg_exp      = '/"widgetType":"([^"]*)/i';
			$output_array = array();

			if ( is_array( $elementor_data[0] ) ) {
				$elementor_data[0] = wp_json_encode( $elementor_data[0] );
			}

			preg_match_all( $reg_exp, $elementor_data[0], $output_array, PREG_SET_ORDER );

			$widgets_list = array();

			foreach ( $output_array as $found ) {
				if ( ! isset( $found[1] ) ) {
					continue;
				}

				$widget_key = $found[1];

				$widgets_list[] = $widget_key;
			}

			return $widgets_list;
		}
		return array();
	}

	/**
	 * Check if the Checkout page is using Elementor.
	 *
	 * @param int $page_id The ID of the page.
	 *
	 * @return bool
	 */
	public static function has_elementor_checkout( int $page_id = 0 ): bool {
		// Check if Elementor is installed and activated.
		if ( did_action( 'elementor/loaded' ) ) {
			if ( $page_id ) {
				$elementor_widgets = self::get_elementor_widgets( $page_id );
			} else {
				// Check the WooCommerce checkout page.
				$elementor_widgets = self::get_elementor_widgets( wc_get_page_id( 'checkout' ) );
			}

			if ( $elementor_widgets ) {
				return in_array( 'woocommerce-checkout-page', $elementor_widgets, true );
			}
		}

		return false;
	}

	/**
	 * Check if the Cart page is using Elementor.
	 *
	 * @return bool
	 */
	public static function has_elementor_cart(): bool {
		$elementor_widgets = self::get_elementor_widgets( wc_get_page_id( 'cart' ) );

		if ( $elementor_widgets ) {
			return in_array( 'woocommerce-cart-page', $elementor_widgets, true );
		}

		return false;
	}

	/**
	 * Check if the Checkout page is using the block checkout.
	 *
	 * @return bool
	 */
	public static function has_block_checkout(): bool {
		$checkout_page_id = wc_get_page_id( 'checkout' );
		return $checkout_page_id && has_block( 'woocommerce/checkout', $checkout_page_id );
	}

	/**
	 * Check if the Cart page is using the block cart.
	 *
	 * @return bool
	 */
	public static function has_block_cart(): bool {
		$cart_page_id = wc_get_page_id( 'cart' );
		return $cart_page_id && has_block( 'woocommerce/cart', $cart_page_id );
	}

	/**
	 * Check if the Checkout page is using the classic checkout.
	 *
	 * @return bool
	 */
	public static function has_classic_checkout(): bool {
		$checkout_page_id = wc_get_page_id( 'checkout' );
		return $checkout_page_id && ( has_block( 'woocommerce/classic-shortcode', $checkout_page_id ) || self::has_classic_shortcode( $checkout_page_id, 'woocommerce_checkout' ) );
	}

	/**
	 * Check if the Cart page is using the classic cart.
	 *
	 * @return bool
	 */
	public static function has_classic_cart(): bool {
		$cart_page_id = wc_get_page_id( 'cart' );
		return $cart_page_id && ( has_block( 'woocommerce/classic-shortcode', $cart_page_id ) || self::has_classic_shortcode( $cart_page_id, 'woocommerce_cart' ) );
	}

	/**
	 * Check if a page has a specific shortcode.
	 *
	 * @param int    $page_id   The ID of the page.
	 * @param string $shortcode The shortcode to check for.
	 *
	 * @return bool
	 */
	private static function has_classic_shortcode( int $page_id, string $shortcode ): bool {
		if ( ! $page_id ) {
			return false;
		}

		$page         = get_post( $page_id );
		$page_content = is_object( $page ) ? $page->post_content : '';

		return str_contains( $page_content, $shortcode );
	}
}
