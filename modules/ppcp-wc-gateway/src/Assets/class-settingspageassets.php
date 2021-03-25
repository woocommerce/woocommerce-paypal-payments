<?php

/**
 * Register and configure assets provided by this module.
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

class SettingsPageAssets {

	/**
	 * @var string
	 */
	private $module_url;

	/**
	 * Assets constructor.
	 *
	 * @param string $module_url The url of this module.
	 */
	public function __construct( string $module_url ) {
		$this->module_url = $module_url;
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
		add_action(
			'admin_enqueue_scripts',
			function() {
				wp_enqueue_script(
					'ppcp-gateway-settings',
					trailingslashit( $this->module_url ) . 'assets/js/gateway-settings.js',
					array(),
					null,
					true
				);
			}
		);
	}
}
