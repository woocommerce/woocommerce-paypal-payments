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
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;

return array(
	'agentic.response.factory'  => static function (): ResponseFactory {
		return new ResponseFactory();
	},
	'agentic.auth.key_provider' => static function (): PayPalJwkProvider {
		return new PayPalJwkProvider();
	},
	'agentic.auth.service'      => static function ( ContainerInterface $c ): JwtAuthService {
		return new JwtAuthService(
			$c->get( 'agentic.auth.key_provider' )
		);
	},

	// REST endpoints.

	'agentic.rest.create_cart'  => static function ( ContainerInterface $c ): CreateCartEndpoint {
		return new CreateCartEndpoint(
			$c->get( 'agentic.auth.service' ),
			$c->get( 'agentic.response.factory' ),
		);
	},
);
