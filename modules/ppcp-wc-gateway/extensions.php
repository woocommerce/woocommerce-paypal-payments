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

	'api.merchant_email'             => static function ( string $previous, ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'merchant_email' ) ? (string) $settings->get( 'merchant_email' ) : '';
	},
	'api.merchant_id'                => static function ( string $previous, ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'merchant_id' ) ? (string) $settings->get( 'merchant_id' ) : '';
	},
	'api.partner_merchant_id'        => static function ( string $previous, ContainerInterface $container ): string {
		$environment = $container->get( 'onboarding.environment' );

		/**
		 * The environment.
		 *
		 * @var Environment $environment
		 */
		return $environment->current_environment_is( Environment::SANDBOX ) ?
			(string) $container->get( 'api.partner_merchant_id-sandbox' ) : (string) $container->get( 'api.partner_merchant_id-production' );
	},
	'api.key'                        => static function ( string $previous, ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		$key      = $settings->has( 'client_id' ) ? (string) $settings->get( 'client_id' ) : '';
		return $key;
	},
	'api.secret'                     => static function ( string $previous, ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'client_secret' ) ? (string) $settings->get( 'client_secret' ) : '';
	},
	'api.prefix'                     => static function ( string $previous, ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		return $settings->has( 'prefix' ) ? (string) $settings->get( 'prefix' ) : 'WC-';
	},
	'woocommerce.logger.woocommerce' => function ( LoggerInterface $previous, ContainerInterface $container ): LoggerInterface {
		if ( ! function_exists( 'wc_get_logger' ) || ! $container->get( 'wcgateway.logging.is-enabled' ) ) {
			return new NullLogger();
		}

		$source = $container->get( 'woocommerce.logger.source' );
		return new WooCommerceLogger(
			wc_get_logger(),
			$source
		);
	},
	'wcgateway.settings.fields'      => function ( array $fields, ContainerInterface $container ): array {
		$files = array(
			'paypal-smart-button-fields.php',
			'connection-tab-fields.php',
			'pay-later-tab-fields.php',
			'card-button-fields.php',
		);

		return array_merge(
			...array_map(
				function ( string $file ) use ( $container, $fields ): array {
					$path_to_settings_fields = __DIR__ . '/src/Settings/Fields/';
					/**
					 * Skip path check.
					 *
					 * @psalm-suppress UnresolvableInclude
					 */
					$get_fields = require $path_to_settings_fields . $file;
					return $get_fields( $container, $fields ) ?? array();
				},
				$files
			)
		);
	},
);
