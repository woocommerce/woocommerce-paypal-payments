<?php
/**
 * The order endpoints module services.
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints;

use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'order-endpoints.request-data'                => static function ( ContainerInterface $container ): RequestData {
		return new RequestData();
	},
	'order-endpoints.helper.cart-products'        => static function ( ContainerInterface $container ): CartProductsHelper {
		$data_store = \WC_Data_Store::load( 'product' );
		return new CartProductsHelper( $data_store );
	},
	'order-endpoints.helper.early-order-handler'  => static function ( ContainerInterface $container ): EarlyOrderHandler {
		return new EarlyOrderHandler(
			$container->get( 'settings.flag.is-connected' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'session.handler' )
		);
	},
	'order-endpoints.helper.wc-order-creator'     => static function ( ContainerInterface $container ): WooCommerceOrderCreator {
		return new WooCommerceOrderCreator(
			$container->get( 'wcgateway.funding-source.renderer' ),
			$container->get( 'session.handler' ),
			$container->get( 'wc-subscriptions.helper' ),
			$container->get( 'button.session.factory.card-data' ),
			$container->get( 'api.factory.shipping' ),
			$container->get( 'api.factory.payer' )
		);
	},
);
