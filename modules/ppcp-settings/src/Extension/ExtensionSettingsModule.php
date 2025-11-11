<?php
/**
 * Base class for settings extension modules.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Extension;

abstract class ExtensionSettingsModule {

	/**
	 * Relative path to the module's assets directory.
	 *
	 * Example: 'modules/ppcp-agentic-commerce/assets/'
	 */
	protected const ASSETS_DIR = '';

	/**
	 * Script handle for wp_enqueue_script.
	 *
	 * Example: 'ppcp-agentic-commerce-settings'
	 */
	protected const SCRIPT_HANDLE = '';

	private string $absolute_plugin_path;
	private string $plugin_main_file;
	private ExtensionRestEndpoint $settings_endpoint;

	public function __construct(
		string $absolute_plugin_path,
		string $plugin_main_file,
		ExtensionRestEndpoint $settings_endpoint
	) {

		$this->absolute_plugin_path = $absolute_plugin_path;
		$this->plugin_main_file     = $plugin_main_file;
		$this->settings_endpoint    = $settings_endpoint;
	}

	/**
	 * Initializes the settings extension.
	 *
	 * Registers WordPress hooks for script enqueuing and REST endpoint registration.
	 */
	public function init(): void {
		add_action(
			'woocommerce_paypal_payments_settings_scripts_enqueued',
			fn() => $this->enqueue_settings_script()
		);

		add_action(
			'rest_api_init',
			fn() => $this->settings_endpoint->register_routes()
		);
	}

	/**
	 * Enqueues the settings JavaScript module.
	 */
	private function enqueue_settings_script(): void {
		$assets_path = trailingslashit( $this->absolute_plugin_path . static::ASSETS_DIR );
		$assets_url  = trailingslashit( plugins_url( static::ASSETS_DIR, $this->plugin_main_file ) );

		/** @psalm-suppress UnresolvableInclude - webpack generates this file */
		$script_asset_file = require $assets_path . 'settings.asset.php';

		wp_enqueue_script(
			static::SCRIPT_HANDLE,
			$assets_url . 'settings.js',
			$script_asset_file['dependencies'],
			$script_asset_file['version'],
			true
		);
	}
}
