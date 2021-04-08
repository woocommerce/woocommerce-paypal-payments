<?php
/**
 * Register and configure assets provided by this module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

/**
 * Class SettingsPageAssets
 */
class SettingsPageAssets {

	/**
	 * The URL of this module.
	 *
	 * @var string
	 */
	private $module_url;
	/**
	 * The filesystem path to the module dir.
	 *
	 * @var string
	 */
	private $module_path;

	/**
	 * Assets constructor.
	 *
	 * @param string $module_url The url of this module.
	 * @param string $module_path The filesystem path to this module.
	 */
	public function __construct( string $module_url, string $module_path ) {
		$this->module_url  = $module_url;
		$this->module_path = $module_path;
	}

	/**
	 * Register assets provided by this module.
	 */
	public function register_assets() {
		add_action(
			'admin_enqueue_scripts',
			function() {
				if ( ! is_admin() || is_ajax() ) {
					return;
				}

				if ( ! $this->is_paypal_payment_method_page() ) {
					return;
				}

				$this->register_admin_assets();
			}
		);

	}

	/**
	 * Check whether the current page is PayPal payment method settings.
	 *
	 * @return bool
	 */
	private function is_paypal_payment_method_page(): bool {

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		$tab     = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );

		if ( ! 'woocommerce_page_wc-settings' === $screen->id ) {
			return false;
		}

		return 'checkout' === $tab && 'ppcp-gateway' === $section;
	}

	/**
	 * Register assets for admin pages.
	 */
	private function register_admin_assets() {
		$gateway_settings_script_path = trailingslashit( $this->module_path ) . 'assets/js/gateway-settings.js';

		wp_enqueue_script(
			'ppcp-gateway-settings',
			trailingslashit( $this->module_url ) . 'assets/js/gateway-settings.js',
			array(),
			file_exists( $gateway_settings_script_path ) ? (string) filemtime( $gateway_settings_script_path ) : null,
			true
		);
	}
}
