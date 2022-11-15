<?php
/**
 * The logging services.
 *
 * @package WooCommerce\WooCommerce\Logging
 */

declare(strict_types=1);

namespace WooCommerce\WooCommerce\Logging;

use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\WooCommerce\Logging\Logger\WooCommerceLogger;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return array(
	'woocommerce.logger.source'      => function(): string {
		return 'woocommerce-paypal-payments';
	},
	'woocommerce.logger.woocommerce' => function ( ContainerInterface $container ): LoggerInterface {
		if ( ! class_exists( \WC_Logger::class ) ) {
			return new NullLogger();
		}

		$source = $container->get( 'woocommerce.logger.source' );

		return new WooCommerceLogger(
			wc_get_logger(),
			$source
		);
	},
);
