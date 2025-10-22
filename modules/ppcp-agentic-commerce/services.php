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
	ResponseFactory::class    => static fn(): ResponseFactory => new ResponseFactory(),
	PayPalJwkProvider::class  => static fn(): PayPalJwkProvider => new PayPalJwkProvider(),
	JwtAuthService::class     => static fn( ContainerInterface $c ): JwtAuthService => new JwtAuthService(
		$c->get( PayPalJwkProvider::class )
	),
	CreateCartEndpoint::class => static fn(): CreateCartEndpoint => new CreateCartEndpoint(),
);
