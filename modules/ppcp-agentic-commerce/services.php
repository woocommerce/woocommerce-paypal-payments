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
	'agentic.response.factory'  => static fn(): ResponseFactory => new ResponseFactory(),
	'agentic.auth.key_provider' => static fn(): PayPalJwkProvider => new PayPalJwkProvider(),
	'agentic.auth.service'      => static fn( ContainerInterface $c ): JwtAuthService => new JwtAuthService(
		$c->get( 'agentic.auth.key_provider' )
	),

	'agentic.rest.create_cart'  => static fn( ContainerInterface $c ): CreateCartEndpoint => new CreateCartEndpoint(
		$c->get( 'agentic.auth.service' ),
		$c->get( 'agentic.response.factory' ),
	),
);
