<?php
/**
 * The agentic commerce services.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;


use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'agentic.ingestion-batch-provider' => static function (ContainerInterface $container ) {
		return new IngestionBatchProvider();
	},
	'agentic.ingestion-manager' => static function (ContainerInterface $container ) {
		return new IngestionManager(
			$container->get('agentic.ingestion-batch-provider'),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	}
);
