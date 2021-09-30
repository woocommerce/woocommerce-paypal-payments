<?php
/**
 * Register and configure assets provided by this module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

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
	 * The bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * Assets constructor.
	 *
	 * @param string $module_url The url of this module.
	 * @param string $module_path The filesystem path to this module.
	 * @param Bearer $bearer The bearer.
	 */
	public function __construct( string $module_url, string $module_path, Bearer $bearer ) {
		$this->module_url  = $module_url;
		$this->module_path = $module_path;
		$this->bearer      = $bearer;
	}

	/**
	 * Register assets provided by this module.
	 */
	public function register_assets() {
		$bearer = $this->bearer;
		add_action(
			'admin_enqueue_scripts',
			function() use ( $bearer ) {
				if ( ! is_admin() || is_ajax() ) {
					return;
				}

				if ( ! $this->is_paypal_payment_method_page() ) {
					return;
				}

				$this->register_admin_assets( $bearer );
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
	 *
	 * @param Bearer $bearer The bearer.
	 */
	private function register_admin_assets( Bearer $bearer ) {
		$gateway_settings_script_path = trailingslashit( $this->module_path ) . 'assets/js/gateway-settings.js';

		wp_enqueue_script(
			'ppcp-gateway-settings',
			trailingslashit( $this->module_url ) . 'assets/js/gateway-settings.js',
			array(),
			file_exists( $gateway_settings_script_path ) ? (string) filemtime( $gateway_settings_script_path ) : null,
			true
		);

		try {
			$token = $bearer->bearer();
			wp_localize_script(
				'ppcp-gateway-settings',
				'PayPalCommerceGatewaySettings',
				array(
					'vaulting_features_available' => $token->vaulting_available(),
				)
			);
		} catch ( RuntimeException $exception ) {
			return;
		}
	}
}
