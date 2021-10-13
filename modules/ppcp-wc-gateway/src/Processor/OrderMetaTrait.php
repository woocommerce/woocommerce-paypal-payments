<?php
/**
 * Adds common metadata to the order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Trait OrderMetaTrait.
 */
trait OrderMetaTrait {

	/**
	 * Adds common metadata to the order.
	 *
	 * @param WC_Order    $wc_order The WC order to which metadata will be added.
	 * @param Order       $order The PayPal order.
	 * @param Environment $environment The environment.
	 */
	protected function add_paypal_meta(
		WC_Order $wc_order,
		Order $order,
		Environment $environment
	): void {
		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, $order->id() );
		$wc_order->update_meta_data( PayPalGateway::INTENT_META_KEY, $order->intent() );
		$wc_order->update_meta_data(
			PayPalGateway::ORDER_PAYMENT_MODE_META_KEY,
			$environment->current_environment_is( Environment::SANDBOX ) ? 'sandbox' : 'live'
		);
	}
}
