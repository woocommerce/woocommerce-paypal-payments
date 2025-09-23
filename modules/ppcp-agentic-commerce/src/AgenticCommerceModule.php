<?php
/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\AgenticRestEndpoint;

/**
 * Entry point that integrates agentic commerce logic with the plugin's DI system.
 */
class AgenticCommerceModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	public function run( ContainerInterface $container ): bool {
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$endpoints = array(
					'create_cart' => $container->get( 'agentic.rest.create_cart' ),
				);

				foreach ( $endpoints as $endpoint ) {
					assert( $endpoint instanceof AgenticRestEndpoint );
					$endpoint->register_routes();
				}
			}
		);

		return true;
	}
}
