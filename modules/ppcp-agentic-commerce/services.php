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
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsDataModel;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsModule;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;

return array(
	'agentic.config.webhook_urls'              => static function ( ContainerInterface $c ): AgenticWebhookConfiguration {
		return new AgenticWebhookConfiguration(
			$c->get( 'settings.connection-state' ),
		);
	},
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
			$c->get( 'agentic.config.webhook_urls' ),
			$c->get( 'agentic.merchant.provider' )
		);
	},

	// Authentication services.
	'agentic.auth.key_provider'                => static function (): PayPalJwkProvider {
		return new PayPalJwkProvider();
	},
	'agentic.auth.provider'                    => static function ( ContainerInterface $c ): AuthServiceProvider {
		return new AuthServiceProvider(
			$c->get( 'settings.connection-state' ),
			$c->get( 'agentic.auth.key_provider' ),
			$c->get( 'agentic.merchant.provider' )
		);
	},

	// Session management.
	'agentic.session.handler'                  => static function (): AgenticSessionHandler {
		return new AgenticSessionHandler();
	},

	// REST endpoints.
	'agentic.rest.create_cart'                 => static function ( ContainerInterface $c ): CreateCartEndpoint {
		return new CreateCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' )
		);
	},
	'agentic.rest.get_cart'                    => static function ( ContainerInterface $c ): GetCartEndpoint {
		return new GetCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' )
		);
	},
	'agentic.rest.update_cart'                 => static function ( ContainerInterface $c ): UpdateCartEndpoint {
		return new UpdateCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' )
		);
	},
	'agentic.rest.replace_cart'                => static function ( ContainerInterface $c ): ReplaceCartEndpoint {
		return new ReplaceCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' )
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
	'agentic.ingestion-batch-provider'         => static function ( ContainerInterface $c ): Ingestion\IngestionBatchProvider {
		return new Ingestion\IngestionBatchProvider(
			$c->get( 'agentic.ingestion-stale-timeout-days' ),
			$c->get( 'agentic.ingestion-eligible-product-types' )
		);
	},
	'agentic.ingestion-manager'                => static function ( ContainerInterface $c ): Ingestion\IngestionManager {
		return new Ingestion\IngestionManager(
			$c->get( 'agentic.ingestion-batch-provider' ),
			$c->get( 'agentic.config.webhook_urls' ),
			$c->get( 'woocommerce.logger.woocommerce' )
		);
	},

	// Settings.
	'agentic.settings.model'                   => static function (): AgenticSettingsDataModel {
		return new AgenticSettingsDataModel();
	},
	'agentic.settings.endpoint'                => static function ( ContainerInterface $c ): AgenticSettingsEndpoint {
		return new AgenticSettingsEndpoint(
			$c->get( 'agentic.settings.model' )
		);
	},
	'agentic.settings.module'                  => static function ( ContainerInterface $c ): AgenticSettingsModule {
		return new AgenticSettingsModule(
			$c->get( 'ppcp.path-to-plugin-folder' ),
			$c->get( 'ppcp.path-to-plugin-main-file' ),
			$c->get( 'agentic.settings.endpoint' ),
			$c->get( 'agentic.registration.eligibility' )
		);
	},
);
