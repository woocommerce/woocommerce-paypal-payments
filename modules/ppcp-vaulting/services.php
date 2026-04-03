<?php
/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'vaulting.payment-token-factory'      => function ( ContainerInterface $container ): PaymentTokenFactory {
		return new PaymentTokenFactory();
	},
	'vaulting.payment-token-helper'       => function ( ContainerInterface $container ): PaymentTokenHelper {
		return new PaymentTokenHelper();
	},
	'vaulting.wc-payment-tokens'          => static function ( ContainerInterface $container ): WooCommercePaymentTokens {
		return new WooCommercePaymentTokens(
			$container->get( 'vaulting.payment-token-helper' ),
			$container->get( 'vaulting.payment-token-factory' ),
			$container->get( 'api.endpoint.payment-tokens' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
