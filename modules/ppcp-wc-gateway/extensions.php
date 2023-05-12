<?php
/**
 * The extensions of the gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use WooCommerce\PayPalCommerce\Onboarding\Environment;
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
		$path_to_settings_fields = __DIR__ . '/src/Settings/Fields';

		$get_paypal_button_fields = require $path_to_settings_fields . '/paypal-smart-button-fields.php';
		$paypal_button_fields = $get_paypal_button_fields( $container, $fields ) ?? array();

		$get_connection_tab_fields = require $path_to_settings_fields . '/connection-tab-fields.php';
		$connection_tab_fields = $get_connection_tab_fields( $container, $fields ) ?? array();

		$get_pay_later_tab_fields = require $path_to_settings_fields . '/pay-later-tab-fields.php';
		$pay_later_tab_fields = $get_pay_later_tab_fields( $container, $fields ) ?? array();

		return array_merge( $paypal_button_fields, $connection_tab_fields, $pay_later_tab_fields );
	},
);
