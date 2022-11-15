<?php
/**
 * The extensions of the gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\WooCommerce\Logging\Logger\WooCommerceLogger;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return array(

	'api.merchant_email'             => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'merchant_email' ) ? (string) $settings->get( 'merchant_email' ) : '';
	},
	'api.merchant_id'                => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'merchant_id' ) ? (string) $settings->get( 'merchant_id' ) : '';
	},
	'api.partner_merchant_id'        => static function ( ContainerInterface $container ): string {
		$environment = $container->get( 'onboarding.environment' );

		/**
		 * The environment.
		 *
		 * @var Environment $environment
		 */
		return $environment->current_environment_is( Environment::SANDBOX ) ?
			(string) $container->get( 'api.partner_merchant_id-sandbox' ) : (string) $container->get( 'api.partner_merchant_id-production' );
	},
	'api.key'                        => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		$key      = $settings->has( 'client_id' ) ? (string) $settings->get( 'client_id' ) : '';
		return $key;
	},
	'api.secret'                     => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'client_secret' ) ? (string) $settings->get( 'client_secret' ) : '';
	},
	'api.prefix'                     => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'prefix' ) ? (string) $settings->get( 'prefix' ) : 'WC-';
	},
	'api.endpoint.order'             => static function ( ContainerInterface $container ): OrderEndpoint {
		$order_factory           = $container->get( 'api.factory.order' );
		$patch_collection_factory = $container->get( 'api.factory.patch-collection-factory' );
		$logger                 = $container->get( 'woocommerce.logger.woocommerce' );
		/**
		 * The session handler.
		 *
		 * @var SessionHandler $session_handler
		 */
		$session_handler = $container->get( 'session.handler' );
		$bn_code         = $session_handler->bn_code();

		/**
		 * The settings.
		 *
		 * @var Settings $settings
		 */
		$settings                     = $container->get( 'wcgateway.settings' );
		$intent                       = $settings->has( 'intent' ) && strtoupper( (string) $settings->get( 'intent' ) ) === 'AUTHORIZE' ? 'AUTHORIZE' : 'CAPTURE';
		$application_context_repository = $container->get( 'api.repository.application-context' );
		$pay_pal_request_id_repository              = $container->get( 'api.repository.paypal-request-id' );
		$subscription_helper = $container->get( 'subscription.helper' );
		return new OrderEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$order_factory,
			$patch_collection_factory,
			$intent,
			$logger,
			$application_context_repository,
			$pay_pal_request_id_repository,
			$subscription_helper,
			$bn_code
		);
	},
	'woocommerce.logger.woocommerce' => function ( ContainerInterface $container ): LoggerInterface {
		if ( ! function_exists( 'wc_get_logger' ) || ! $container->get( 'wcgateway.logging.is-enabled' ) ) {
			return new NullLogger();
		}

		$source = $container->get( 'woocommerce.logger.source' );
		return new WooCommerceLogger(
			wc_get_logger(),
			$source
		);
	},

	'wcgateway.settings.fields'      => function ( ContainerInterface $container, array $fields ): array {
		$get_connection_tab_fields = require __DIR__ . '/connection-tab-settings.php';
		$connection_tab_fields = $get_connection_tab_fields( $container, $fields ) ?? array();

		$get_pay_later_tab_fields = require __DIR__ . '/pay-later-tab-settings.php';
		$pay_later_tab_fields = $get_pay_later_tab_fields( $container, $fields ) ?? array();

		return array_merge( $connection_tab_fields, $pay_later_tab_fields );
	},
);
