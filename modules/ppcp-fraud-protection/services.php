<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'fraud-protection.url' => static function ( ContainerInterface $container ): string {
		return plugins_url( '/modules/fraud-protection/', $container->get( 'ppcp.path-to-plugin-main-file' ) );
	},
);
