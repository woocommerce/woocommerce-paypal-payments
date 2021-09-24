<?php
/**
 * The services
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;

return array(
	'subscription.helper'          => static function ( $container ): SubscriptionHelper {
		return new SubscriptionHelper();
	},
	'subscription.renewal-handler' => static function ( $container ): RenewalHandler {
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		$repository            = $container->get( 'vaulting.repository.payment-token' );
		$endpoint              = $container->get( 'api.endpoint.order' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		return new RenewalHandler(
			$logger,
			$repository,
			$endpoint,
			$purchase_unit_factory,
			$payer_factory
		);
	},
);
