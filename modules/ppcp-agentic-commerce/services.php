<?php
/**
 * The agentic commerce services.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Automattic\WooCommerce\Enums\ProductType;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CheckoutEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\GetCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\UpdateCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\ReplaceCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

return array(
	'agentic.response.factory'                 => static function (): ResponseFactory {
		return new ResponseFactory();
	},

	// Authentication services.
	'agentic.auth.key_provider'                => static function (): PayPalJwkProvider {
		return new PayPalJwkProvider();
	},
	'agentic.auth.service'                     => static function ( ContainerInterface $c ): JwtAuthService {
		return new JwtAuthService(
			$c->get( 'agentic.auth.key_provider' )
		);
	},

	// Session management.
	'agentic.session.handler'                  => static function ( ContainerInterface $container ): AgenticSessionHandler {
		return new AgenticSessionHandler();
	},

	// REST endpoints.
	'agentic.rest.create_cart'                 => static function ( ContainerInterface $c ): CreateCartEndpoint {
		return new CreateCartEndpoint(
			$c->get( 'agentic.auth.service' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' )
		);
	},

	'agentic.rest.get_cart'                    => static function ( ContainerInterface $container ): GetCartEndpoint {
		return new GetCartEndpoint(
			$container->get( 'agentic.auth.service' ),
			$container->get( 'agentic.session.handler' ),
			$container->get( 'agentic.response.factory' )
		);
	},

	'agentic.rest.update_cart'                 => static function ( ContainerInterface $container ): UpdateCartEndpoint {
		return new UpdateCartEndpoint(
			$container->get( 'agentic.auth.service' ),
			$container->get( 'agentic.session.handler' ),
			$container->get( 'agentic.response.factory' )
		);
	},

	'agentic.rest.replace_cart'                => static function ( ContainerInterface $container ): ReplaceCartEndpoint {
		return new ReplaceCartEndpoint(
			$container->get( 'agentic.auth.service' ),
			$container->get( 'agentic.session.handler' ),
			$container->get( 'agentic.response.factory' )
		);
	},

	'agentic.rest.checkout'                    => static function ( ContainerInterface $container ): CheckoutEndpoint {
		return new CheckoutEndpoint(
			$container->get( 'agentic.auth.service' ),
			$container->get( 'agentic.session.handler' ),
			$container->get( 'agentic.response.factory' )
		);
	},

	// Ingestion services.
	'agentic.ingestion-eligible-product-types' => static function ( ContainerInterface $container ) {
		return array(
			ProductType::SIMPLE,
			ProductType::VARIABLE,
		);
	},
	'agentic.ingestion-stale-timeout-days'     => static function ( ContainerInterface $container ) {
		return 5;
	},
	'agentic.ingestion-api-endpoint'           => static function ( ContainerInterface $container ) {
		return 'https://d-staging.joinhoney.com/webhooks/products';
	},
	'agentic.sync-job-factory'                 => static function ( ContainerInterface $container ) {
		return new Ingestion\SyncJobFactory(
			$container->get( 'agentic.ingestion-api-endpoint' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'agentic.ingestion-batch-provider'         => static function ( ContainerInterface $container ): Ingestion\IngestionBatchProvider {
		return new Ingestion\IngestionBatchProvider(
			$container->get( 'agentic.ingestion-stale-timeout-days' ),
			$container->get( 'agentic.ingestion-eligible-product-types' )
		);
	},
	'agentic.ingestion-manager'                => static function ( ContainerInterface $container ): Ingestion\IngestionManager {
		return new Ingestion\IngestionManager(
			$container->get( 'agentic.ingestion-batch-provider' ),
			$container->get( 'agentic.sync-job-factory' )
		);
	},
);
