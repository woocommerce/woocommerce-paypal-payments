<?php
/**
 * Settings notice generator.
 * Generates the settings notices.
 *
 * @package WooCommerce\PayPalCommerce\Axo\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;

/**
 * Class SettingsNoticeGenerator
 */
class SettingsNoticeGenerator {

	/**
	 * Generates the checkout notice.
	 *
	 * @return string
	 */
	public function generate_checkout_notice(): string {
		$checkout_page_link       = esc_url( get_edit_post_link( wc_get_page_id( 'checkout' ) ) ?? '' );
		$block_checkout_docs_link = __(
			'https://woocommerce.com/document/cart-checkout-blocks-status/#reverting-to-the-cart-and-checkout-shortcodes',
			'woocommerce-paypal-payments'
		);

		$notice_content = '';

		if ( CartCheckoutDetector::has_elementor_checkout() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store currently uses the <code>Elementor Checkout widget</code>. To enable Fastlane and accelerate payments, the page must include either the <code>Classic Checkout</code> or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the classic layout.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		} elseif ( CartCheckoutDetector::has_block_checkout() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store currently uses the WooCommerce <code>Checkout</code> block. To enable Fastlane and accelerate payments, the page must include either the <code>Classic Checkout</code> or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the classic layout.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		} elseif ( ! CartCheckoutDetector::has_classic_checkout() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store does not seem to be properly configured or uses an incompatible <code>third-party Checkout</code> solution. To enable Fastlane and accelerate payments, the page must include either the <code>Classic Checkout</code> or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the classic layout.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		}

		return $notice_content ? '<div class="ppcp-notice ppcp-notice-error"><p>' . $notice_content . '</p></div>' : '';
	}

	/**
	 * Generates the shipping notice.
	 *
	 * @return string
	 */
	public function generate_shipping_notice(): string {
		$shipping_settings_link = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' );

		$notice_content = '';

		if ( wc_shipping_enabled() && wc_ship_to_billing_address_only() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Shipping destination settings page. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Shipping destination</a> of your store is currently configured to <code>Force shipping to the customer billing address</code>. To enable Fastlane and accelerate payments, the shipping destination must be configured either to <code>Default to customer shipping address</code> or <code>Default to customer billing address</code> so buyers can set separate billing and shipping details.',
					'woocommerce-paypal-payments'
				),
				esc_url( $shipping_settings_link )
			);
		}

		return $notice_content ? '<div class="ppcp-notice ppcp-notice-error"><p>' . $notice_content . '</p></div>' : '';
	}
}
