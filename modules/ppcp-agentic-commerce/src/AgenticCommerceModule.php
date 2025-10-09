<?php
/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

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
	 */
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
		// Add hooks.
		add_action(
			'init',
			function () use ( $container ) {
				$ingestion_manager = $container->get( 'agentic.ingestion-manager' );
				assert( $ingestion_manager instanceof IngestionManager );
				$ingestion_manager->init();

//				do_action('ppcp_agentic_sync_batch');
			}
		);

		return true;
	}
}
