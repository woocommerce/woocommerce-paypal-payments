<?php
/**
 * The agentic commerce services.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Automattic\WooCommerce\Enums\ProductType;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;

return array(
	'agentic.response.factory'  => static function (): ResponseFactory {
		return new ResponseFactory();
	},
	'agentic.auth.key_provider' => static function (): PayPalJwkProvider {
		return new PayPalJwkProvider();
	},
	'agentic.auth.service'      => static function ( ContainerInterface $c ): JwtAuthService {
		return new JwtAuthService(
			$c->get( 'agentic.auth.key_provider' )
		);
	},

	// REST endpoints.

	'agentic.rest.create_cart'  => static function ( ContainerInterface $c ): CreateCartEndpoint {
		return new CreateCartEndpoint(
			$c->get( 'agentic.auth.service' ),
			$c->get( 'agentic.response.factory' ),
		);
	},

	// Ingestion

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
		return 'https://d.joinhoney.com/webhooks/products';
	},
	'agentic.sync-job-factory'                 => static function ( ContainerInterface $container ) {
		return new SyncJobFactory(
			$container->get( 'agentic.ingestion-api-endpoint' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'agentic.ingestion-batch-provider'         => static function ( ContainerInterface $container ) {
		return new IngestionBatchProvider(
			$container->get( 'agentic.ingestion-stale-timeout-days' ),
			$container->get( 'agentic.ingestion-eligible-product-types' )
		);
	},
	'agentic.ingestion-manager'                => static function ( ContainerInterface $container ) {
		return new IngestionManager(
			$container->get( 'agentic.ingestion-batch-provider' ),
			$container->get( 'agentic.sync-job-factory' )
		);
	},
);
