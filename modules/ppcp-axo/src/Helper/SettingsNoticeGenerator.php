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
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class SettingsNoticeGenerator
 */
class SettingsNoticeGenerator {
	/**
	 * The list of Fastlane incompatible plugin names.
	 *
	 * @var string[]
	 */
	protected $incompatible_plugin_names;

	/**
	 * SettingsNoticeGenerator constructor.
	 *
	 * @param string[] $incompatible_plugin_names The list of Fastlane incompatible plugin names.
	 */
	public function __construct( array $incompatible_plugin_names ) {
		$this->incompatible_plugin_names = $incompatible_plugin_names;
	}

	/**
	 * Generates the full HTML of the notification.
	 *
	 * @param string $message  HTML of the inner message contents.
	 * @param bool   $is_error Whether the provided message is an error. Affects the notice color.
	 *
	 * @return string The full HTML code of the notification, or an empty string.
	 */
	private function render_notice( string $message, bool $is_error = false ) : string {
		if ( ! $message ) {
			return '';
		}

		return sprintf(
			'<div class="ppcp-notice %1$s"><p>%2$s</p></div>',
			$is_error ? 'ppcp-notice-error' : '',
			$message
		);
	}

	/**
	 * Generates the checkout notice.
	 *
	 * @return string
	 */
	public function generate_checkout_notice(): string {
		$checkout_page_link       = esc_url( get_edit_post_link( wc_get_page_id( 'checkout' ) ) ?? '' );
		$block_checkout_docs_link = __(
			'https://woocommerce.com/document/woocommerce-store-editing/customizing-cart-and-checkout/#using-the-cart-and-checkout-blocks',
			'woocommerce-paypal-payments'
		);

		$notice_content = '';

		if ( CartCheckoutDetector::has_elementor_checkout() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store currently uses the <code>Elementor Checkout widget</code>. To enable Fastlane and accelerate payments, the page must include either the <code>Checkout</code> block, <code>Classic Checkout</code>, or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the Checkout block.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		} elseif ( ! CartCheckoutDetector::has_classic_checkout() && ! CartCheckoutDetector::has_block_checkout() ) {
			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the block checkout docs. */
				__(
					'<span class="highlight">Warning:</span> The <a href="%1$s">Checkout page</a> of your store does not seem to be properly configured or uses an incompatible <code>third-party Checkout</code> solution. To enable Fastlane and accelerate payments, the page must include either the <code>Checkout</code> block, <code>Classic Checkout</code>, or the <code>[woocommerce_checkout]</code> shortcode. See <a href="%2$s">this page</a> for instructions on how to switch to the Checkout block.',
					'woocommerce-paypal-payments'
				),
				esc_url( $checkout_page_link ),
				esc_url( $block_checkout_docs_link )
			);
		}

		return $notice_content ? '<div class="ppcp-notice ppcp-notice-error"><p>' . $notice_content . '</p></div>' : '';
	}

	/**
	 * Generates the incompatible plugins notice.
	 *
	 * @return string
	 */
	public function generate_incompatible_plugins_notice(): string {
		if ( empty( $this->incompatible_plugin_names ) ) {
			return '';
		}

		$plugins_settings_link = esc_url( admin_url( 'plugins.php' ) );
		$notice_content        = sprintf(
		/* translators: %1$s: URL to the plugins settings page. %2$s: List of incompatible plugins. */
			__(
				'<span class="highlight">Note:</span> The accelerated guest buyer experience provided by Fastlane may not be fully compatible with some of the following <a href="%1$s">active plugins</a>: <ul class="ppcp-notice-list">%2$s</ul>',
				'woocommerce-paypal-payments'
			),
			$plugins_settings_link,
			implode( '', $this->incompatible_plugin_names )
		);

		return '<div class="ppcp-notice"><p>' . $notice_content . '</p></div>';
	}

	/**
	 * Generates a warning notice with instructions on conflicting plugin-internal settings.
	 *
	 * @param Settings $settings The plugin settings container, which is checked for conflicting
	 *                           values.
	 * @return string
	 */
	public function generate_settings_conflict_notice( Settings $settings ) : string {
		$notice_content = '';
		$is_dcc_enabled = false;

		try {
			$is_dcc_enabled = $settings->has( 'dcc_enabled' ) && $settings->get( 'dcc_enabled' );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( NotFoundException $ignored ) {
			// Never happens.
		}

		if ( ! $is_dcc_enabled ) {
			$notice_content = __(
				'<span class="highlight">Warning:</span> To enable Fastlane and accelerate payments, the <strong>Advanced Card Processing</strong> payment method must also be enabled.',
				'woocommerce-paypal-payments'
			);
		}

		return $this->render_notice( $notice_content, true );
	}
}
