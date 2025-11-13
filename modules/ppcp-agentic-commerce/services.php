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
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\GetCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\UpdateCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\ReplaceCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsDataModel;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsModule;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;

return array(
	'agentic.response.factory'                 => static function (): ResponseFactory {
		return new ResponseFactory();
	},
	'agentic.merchant.provider'                => static function ( ContainerInterface $c ): MerchantMetadataProvider {
		return new MerchantMetadataProvider(
			$c->get( 'settings.data.general' )
		);
	},
	'agentic.registration.eligibility'         => static function ( ContainerInterface $c ): RegistrationEligibility {
		return new RegistrationEligibility(
			$c->get( 'agentic.merchant.provider' )
		);
	},
	'agentic.registration.handler'             => static function ( ContainerInterface $c ): RegistrationService {
		return new RegistrationService(
			$c->get( 'settings.connection-state' ),
			$c->get( 'agentic.merchant.provider' )
		);
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
	'agentic.session.handler'                  => static function (): AgenticSessionHandler {
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

	// Ingestion services.
	'agentic.ingestion-eligible-product-types' => static function (): array {
		return array(
			ProductType::SIMPLE,
			ProductType::VARIABLE,
		);
	},
	'agentic.ingestion-stale-timeout-days'     => static function (): int {
		return 5;
	},
	'agentic.ingestion-api-endpoint'           => static function (): string {
		return 'https://d-staging.joinhoney.com/webhooks/products';
	},
	'agentic.sync-job-factory'                 => static function ( ContainerInterface $container ): Ingestion\SyncJobFactory {
		return new Ingestion\SyncJobFactory(
			$container->get( 'agentic.ingestion-api-endpoint' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'agentic.products-payload-factory' ),
		);
	},
	'agentic.products-payload-factory'         => static function ( ContainerInterface $container ) {
		return new Ingestion\ProductsPayloadFactory();
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

	// Settings.
	'agentic.settings.model'                   => static function (): AgenticSettingsDataModel {
		return new AgenticSettingsDataModel();
	},
	'agentic.settings.endpoint'                => static function ( ContainerInterface $container ): AgenticSettingsEndpoint {
		return new AgenticSettingsEndpoint(
			$container->get( 'agentic.settings.model' )
		);
	},
	'agentic.settings.module'                  => static function ( ContainerInterface $container ): AgenticSettingsModule {
		return new AgenticSettingsModule(
			$container->get( 'ppcp.path-to-plugin-folder' ),
			$container->get( 'ppcp.path-to-plugin-main-file' ),
			$container->get( 'agentic.settings.endpoint' ),
			$container->get( 'agentic.registration.eligibility' )
		);
	},
);
