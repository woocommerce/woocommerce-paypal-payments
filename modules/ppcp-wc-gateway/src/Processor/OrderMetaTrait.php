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
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderTransient;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\OrderMetaManager;

/**
 * Trait OrderMetaTrait.
 */
trait OrderMetaTrait {

	/**
	 * Adds common metadata to the order.
	 *
	 * @param WC_Order            $wc_order The WC order to which metadata will be added.
	 * @param Order               $order The PayPal order.
	 * @param Environment         $environment The environment.
	 * @param OrderTransient|null $order_transient The order transient helper.
	 */
	protected function add_paypal_meta(
		WC_Order $wc_order,
		Order $order,
		Environment $environment,
		OrderTransient $order_transient = null
	): void {
		$meta = new OrderMetaManager( $wc_order, $order );
		$meta->set_status();

		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, $order->id() );
		$wc_order->update_meta_data( PayPalGateway::INTENT_META_KEY, $order->intent() );
		$wc_order->update_meta_data(
			PayPalGateway::ORDER_PAYMENT_MODE_META_KEY,
			$environment->current_environment_is( Environment::SANDBOX ) ? 'sandbox' : 'live'
		);
		$payment_source = $this->get_payment_source( $order );
		if ( $payment_source ) {
			$wc_order->update_meta_data( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY, $payment_source );
		}

		$payer = $order->payer();
		if (
			$payer
			&& $payment_source
			&& in_array( $payment_source, PayPalGateway::PAYMENT_SOURCES_WITH_PAYER_EMAIL, true )
		) {
			$payer_email = $payer->email_address();
			if ( $payer_email ) {
				$wc_order->update_meta_data( PayPalGateway::ORDER_PAYER_EMAIL_META_KEY, $payer_email );
			}
		}

		$wc_order->save();

		do_action( 'woocommerce_paypal_payments_woocommerce_order_created', $wc_order, $order );
	}

	/**
	 * Returns the payment source type or null,
	 *
	 * @param Order $order The PayPal order.
	 * @return string|null
	 */
	private function get_payment_source( Order $order ): ?string {
		$source = $order->payment_source();
		if ( $source ) {
			return $source->name();
		}

		return null;
	}
}
