<?php
/**
 * The agentic commerce services.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint;

return array(
	'agentic.response.factory' => static function ( ContainerInterface $container ): ResponseFactory {
		return new ResponseFactory();
	},

	// REST endpoints.

	'agentic.rest.create_cart' => static function ( ContainerInterface $container ): CreateCartEndpoint {
		return new CreateCartEndpoint();
	},
);
