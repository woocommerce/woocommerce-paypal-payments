<?php
/**
 * The Pay Later configurator module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\GetConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'paylater-configurator.url'                  => static function ( ContainerInterface $container ): string {
		/**
		 * The return value must not contain a trailing slash.
		 *
		 * Cannot return false for this path.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-paylater-configurator',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'paylater-configurator.factory.config'       => static function ( ContainerInterface $container ): ConfigFactory {
		return new ConfigFactory();
	},
	'paylater-configurator.endpoint.save-config' => static function ( ContainerInterface $container ): SaveConfig {
		return new SaveConfig(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'button.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'paylater-configurator.endpoint.get-config'  => static function ( ContainerInterface $container ): GetConfig {
		return new GetConfig(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
