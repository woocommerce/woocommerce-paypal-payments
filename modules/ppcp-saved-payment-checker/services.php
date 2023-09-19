<?php
/**
 * The SavedPaymentChecker module services.
 *
 * @package WooCommerce\PayPalCommerce\SavedPaymentChecker
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'saved-payment-checker.payment-token-checker' => function( ContainerInterface $container ) : PaymentTokenChecker {
		return new PaymentTokenChecker(
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'api.repository.order' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.processor.authorized-payments' ),
			$container->get( 'api.endpoint.payments' ),
			$container->get( 'api.endpoint.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
