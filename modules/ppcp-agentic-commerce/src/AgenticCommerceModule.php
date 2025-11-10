<?php
/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion\IngestionManager;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\AgenticRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint;

/**
 * Entry point that integrates agentic commerce logic with the plugin's DI system.
 * This module handles the initialization and execution of the agentic commerce functionality.
 */
class AgenticCommerceModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * Returns the services provided by this module.
	 *
	 * @return array The array of services.
	 * A list of all REST services that this module needs to register on init.
	 */
	private const REST_ENDPOINT_SERVICES = array(
		'agentic.rest.create_cart',
		'agentic.rest.get_cart',
		'agentic.rest.update_cart',
		'agentic.rest.replace_cart',
	);

	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * Runs the module initialization.
	 *
	 * @param ContainerInterface $container The dependency injection container.
	 * @return bool True if the module was initialized successfully.
	 */
	public function run( ContainerInterface $container ): bool {

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				foreach ( self::REST_ENDPOINT_SERVICES as $service_id ) {
					$endpoint = $container->get( $service_id );
					assert( $endpoint instanceof AgenticRestEndpoint );
					$endpoint->register_routes();
				}

				// Internal (settings) endpoint.
				$endpoint = $container->get( 'agentic.settings.endpoint' );
				assert( $endpoint instanceof RestEndpoint );
				$endpoint->register_routes();
			}
		);

		add_action(
			'init',
			function () use ( $container ) {
				$ingestion_manager = $container->get( 'agentic.ingestion-manager' );
				assert( $ingestion_manager instanceof IngestionManager );
				$ingestion_manager->init();
			}
		);

		add_action(
			'woocommerce_paypal_payments_settings_scripts_enqueued',
			fn() => $this->enqueue_scripts(
				'modules/ppcp-agentic-commerce/assets/',
				$container->get( 'ppcp.path-to-plugin-folder' ),
				$container->get( 'ppcp.path-to-plugin-main-file' )
			)
		);

		return true;
	}

	private function enqueue_scripts( string $assets_dir, string $absolute_plugin_path, string $plugin_main_file ): void {
		$assets_path = $absolute_plugin_path . $assets_dir;
		$assets_url  = plugins_url( $assets_dir, $plugin_main_file );

		/** @psalm-suppress UnresolvableInclude */
		$script_asset_file = require $assets_path . '/settings.asset.php';

		wp_register_script(
			'ppcp-agentic-commerce-settings',
			$assets_url . '/settings.js',
			$script_asset_file['dependencies'],
			$script_asset_file['version'],
			true
		);

		wp_enqueue_script( 'ppcp-agentic-commerce-settings' );
	}
}
