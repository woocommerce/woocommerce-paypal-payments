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
		$this->module_url = $module_url;
		$this->module_path = $module_path;
	}

	/**
	 * Register assets provided by this module.
	 */
	public function register_assets() {
		if ( is_admin() && ! is_ajax() ) {
			$this->register_admin_assets();
		}
	}

	/**
	 * Register assets for admin pages.
	 */
	private function register_admin_assets() {
		$gateway_settings_script_path = trailingslashit($this->module_path) . 'assets/js/gateway-settings.js';

		add_action(
			'admin_enqueue_scripts',
			function() use ($gateway_settings_script_path) {
				wp_enqueue_script(
					'ppcp-gateway-settings',
					trailingslashit( $this->module_url ) . 'assets/js/gateway-settings.js',
					array(),
					file_exists($gateway_settings_script_path) ? (string) filemtime($gateway_settings_script_path) : null,
					true
				);
			}
		);
	}
}
