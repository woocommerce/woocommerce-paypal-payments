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
 */
class AgenticCommerceModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	public function run( ContainerInterface $container ): bool {
		// Add hooks.
		add_action(
			'init',
			function () use ( $container ) {
				$ingestionManager = $container->get( 'agentic.ingestion-manager' );
				assert( $ingestionManager instanceof IngestionManager );
				$ingestionManager->init();

//				do_action('ppcp_agentic_sync_batch');
			}
		);

		return true;
	}
}
