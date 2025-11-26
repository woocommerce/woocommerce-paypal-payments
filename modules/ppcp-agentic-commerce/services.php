<?php
/**
 * The agentic commerce services.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Psr\Log\LoggerInterface;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\WooCommerce\Logging\Logger\WooCommerceLogger;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Config\AgenticWebhookConfiguration;
use WooCommerce\PayPalCommerce\AgenticCommerce\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\GetCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\ReplaceCartEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CheckoutEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion\IngestionBatchProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion\IngestionManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsEndpoint;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsDataModel;
use WooCommerce\PayPalCommerce\AgenticCommerce\Setting\AgenticSettingsModule;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;
use WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\InspectionFormHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\InspectionStatusPage;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\AgenticCheckoutProcessor;
use WooCommerce\PayPalCommerce\AgenticCommerce\Cart\PayPalCartToCartDataAdapter;
use WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\InspectionSessionData;
use WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page\RegistrationStatusSection;
use WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page\CartSessionSection;

/**
 * Using a different log-source for agentic commerce log entries makes it much easier to inspect
 * agentic behavior, which is fully decoupled from browser sessions.
 *
 * When using log-files, this creates a separate file for agentic log entries
 * When using DB logging, the source makes it easy to filter for agentic entries
 */
const LOGGER_SOURCE = 'woocommerce-paypal-agentic';

return array(
	// Logging.
	'agentic.logger'                    => static function (): LoggerInterface {
		if ( ! class_exists( \WC_Logger::class ) ) {
			return new NullLogger();
		}

		return new WooCommerceLogger( wc_get_logger(), LOGGER_SOURCE );
	},

	// Configuration.
	'agentic.config.webhook_urls'       => static function ( ContainerInterface $c ): AgenticWebhookConfiguration {
		return new AgenticWebhookConfiguration(
			$c->get( 'settings.connection-state' ),
		);
	},
	'agentic.config.ingestion'          => static function (): IngestionConfiguration {
		return new IngestionConfiguration();
	},

	// Registration and merchant identification.
	'agentic.merchant.provider'         => static function ( ContainerInterface $c ): MerchantMetadataProvider {
		return new MerchantMetadataProvider(
			$c->get( 'settings.data.general' )
		);
	},
	'agentic.registration.eligibility'  => static function ( ContainerInterface $c ): RegistrationEligibility {
		return new RegistrationEligibility(
			$c->get( 'agentic.merchant.provider' )
		);
	},
	'agentic.registration.handler'      => static function ( ContainerInterface $c ): RegistrationService {
		return new RegistrationService(
			$c->get( 'agentic.config.webhook_urls' ),
			$c->get( 'agentic.merchant.provider' ),
			$c->get( 'agentic.logger' )
		);
	},

	// Authentication services.
	'agentic.auth.key_provider'         => static function (): PayPalJwkProvider {
		return new PayPalJwkProvider();
	},
	'agentic.auth.provider'             => static function ( ContainerInterface $c ): AuthServiceProvider {
		return new AuthServiceProvider(
			$c->get( 'settings.connection-state' ),
			$c->get( 'agentic.auth.key_provider' ),
			$c->get( 'agentic.merchant.provider' )
		);
	},

	// Session management.
	'agentic.session.handler'           => static function (): AgenticSessionHandler {
		return new AgenticSessionHandler();
	},

	// Helper services.
	'agentic.helper.cart-adapter'       => static function (): PayPalCartToCartDataAdapter {
		return new PayPalCartToCartDataAdapter();
	},

	'agentic.helper.checkout-processor' => static function ( ContainerInterface $c ): AgenticCheckoutProcessor {
		return new AgenticCheckoutProcessor(
			$c->get( 'api.endpoint.order' ),
			$c->get( 'api.endpoint.orders' ),
			$c->get( 'button.helper.wc-order-creator' ),
			$c->get( 'agentic.helper.cart-adapter' )
		);
	},

	// REST endpoints.
	'agentic.response.factory'          => static function (): ResponseFactory {
		return new ResponseFactory();
	},
	'agentic.rest.create_cart'          => static function ( ContainerInterface $c ): CreateCartEndpoint {
		return new CreateCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.logger' ),
			$c->get( 'api.endpoint.order' ),
			$c->get( 'agentic.helper.cart-adapter' )
		);
	},
	'agentic.rest.get_cart'             => static function ( ContainerInterface $c ): GetCartEndpoint {
		return new GetCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.logger' )
		);
	},
	'agentic.rest.replace_cart'         => static function ( ContainerInterface $c ): ReplaceCartEndpoint {
		return new ReplaceCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.logger' ),
			$c->get( 'api.endpoint.orders' ) // The only difference here is the presence of this line from PCP-5273 vs its absence in PCP-5271. Keeping it from PCP-5273 for completeness, as it seems needed for a replace cart operation.
		);
	},
	'agentic.rest.checkout'             => static function ( ContainerInterface $c ): CheckoutEndpoint {
		return new CheckoutEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.logger' ),
			$c->get( 'agentic.helper.checkout-processor' )
		);
	},

	// Ingestion services.
	'agentic.ingestion-batch-provider'  => static function ( ContainerInterface $c ): IngestionBatchProvider {
		return new IngestionBatchProvider(
			$c->get( 'agentic.config.ingestion' )
		);
	},
	'agentic.ingestion-manager'         => static function ( ContainerInterface $c ): IngestionManager {
		return new IngestionManager(
			$c->get( 'agentic.config.ingestion' ),
			$c->get( 'agentic.ingestion-batch-provider' ),
			$c->get( 'agentic.config.webhook_urls' ),
			$c->get( 'agentic.merchant.provider' ),
			$c->get( 'agentic.logger' )
		);
	},

	// Settings.
	'agentic.settings.model'            => static function (): AgenticSettingsDataModel {
		return new AgenticSettingsDataModel();
	},
	'agentic.settings.endpoint'         => static function ( ContainerInterface $c ): AgenticSettingsEndpoint {
		return new AgenticSettingsEndpoint(
			$c->get( 'agentic.settings.model' )
		);
	},
	'agentic.settings.module'           => static function ( ContainerInterface $c ): AgenticSettingsModule {
		return new AgenticSettingsModule(
			$c->get( 'ppcp.path-to-plugin-folder' ),
			$c->get( 'ppcp.path-to-plugin-main-file' ),
			$c->get( 'agentic.settings.endpoint' ),
			$c->get( 'agentic.registration.eligibility' )
		);
	},

	// Inspector.
	'agentic.inspector.form_handler'    => static function ( ContainerInterface $c ): InspectionFormHandler {
		return new InspectionFormHandler(
			$c->get( 'agentic.registration.handler' ),
			$c->get( 'agentic.logger' )
		);
	},
	'agentic.inspector.session_info'    => static function ( ContainerInterface $c ): InspectionSessionData {
		return new InspectionSessionData();
	},
	'agentic.inspector.page.status'     => static function ( ContainerInterface $c ): RegistrationStatusSection {
		return new RegistrationStatusSection(
			$c->get( 'agentic.registration.handler' ),
			$c->get( 'agentic.registration.eligibility' ),
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'settings.data.general' )
		);
	},
	'agentic.inspector.page.session'    => static function ( ContainerInterface $c ): CartSessionSection {
		return new CartSessionSection(
			$c->get( 'agentic.inspector.session_info' ),
		);
	},
	'agentic.inspector.page'            => static function ( ContainerInterface $c ): InspectionStatusPage {
		return new InspectionStatusPage(
			$c->get( 'agentic.inspector.form_handler' ),
			$c->get( 'agentic.inspector.page.status' ),
			$c->get( 'agentic.inspector.page.session' )
		);
	},
);
