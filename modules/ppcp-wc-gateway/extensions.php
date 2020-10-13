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
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return array(

	'api.merchant_email'             => static function ( $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'merchant_email' ) ? (string) $settings->get( 'merchant_email' ) : '';
	},
	'api.merchant_id'                => static function ( $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'merchant_id' ) ? (string) $settings->get( 'merchant_id' ) : '';
	},
	'api.partner_merchant_id'        => static function ( $container ): string {
		$environment = $container->get( 'onboarding.environment' );

		/**
		 * The environment.
		 *
		 * @var Environment $environment
		 */
		return $environment->current_environment_is( Environment::SANDBOX ) ?
			(string) $container->get( 'api.partner_merchant_id-sandbox' ) : (string) $container->get( 'api.partner_merchant_id-production' );
	},
	'api.key'                        => static function ( $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		$key      = $settings->has( 'client_id' ) ? (string) $settings->get( 'client_id' ) : '';
		return $key;
	},
	'api.secret'                     => static function ( $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'client_secret' ) ? (string) $settings->get( 'client_secret' ) : '';
	},
	'api.prefix'                     => static function ( $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'prefix' ) ? (string) $settings->get( 'prefix' ) : 'WC-';
	},
	'api.endpoint.order'             => static function ( $container ): OrderEndpoint {
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
		return new OrderEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$order_factory,
			$patch_collection_factory,
			$intent,
			$logger,
			$application_context_repository,
			$pay_pal_request_id_repository,
			$bn_code
		);
	},
	'woocommerce.logger.woocommerce' => function ( $container ): LoggerInterface {
		$settings = $container->get( 'wcgateway.settings' );
		if ( ! function_exists( 'wc_get_logger' ) || ! $settings->has( 'logging_enabled' ) || ! $settings->get( 'logging_enabled' ) ) {
			return new NullLogger();
		}

		$source = $container->get( 'woocommerce.logger.source' );
		return new WooCommerceLogger(
			wc_get_logger(),
			$source
		);
	},
);
