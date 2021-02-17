<?php
/**
 * The process_payment functionality for the both gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Trait ProcessPaymentTrait
 */
trait ProcessPaymentTrait {
	/**
	 * Process a payment for an WooCommerce order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;
		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			return null;
		}

		/**
		 * If the WC_Order is payed through the approved webhook.
		 */
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['ppcp-resume-order'] ) && $wc_order->has_status( 'processing' ) ) {
			$this->session_handler->destroy_session_data();
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $wc_order ),
			);
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended

		try {
			if ( $this->order_processor->process( $wc_order, $woocommerce ) ) {
				$this->session_handler->destroy_session_data();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $wc_order ),
				);
			}
		} catch ( PayPalApiException $error ) {
			if ( $error->has_detail( 'INSTRUMENT_DECLINED' ) ) {
				$this->session_handler->increment_insufficient_funding_tries();
				$host = $this->config->has( 'sandbox_on' ) && $this->config->get( 'sandbox_on' ) ?
					'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
				$url  = $host . 'checkoutnow?token=' . $this->session_handler->order()->id();
				if ( $this->session_handler->insufficient_funding_tries() >= 3 ) {
					$this->session_handler->destroy_session_data();
					wc_add_notice(
						__( 'Please use a different payment method.', 'woocommerce-paypal-payments' ),
						'error'
					);
					return null;
				}
				return array(
					'result'   => 'success',
					'redirect' => $url,
				);
			}

			$this->session_handler->destroy_session_data();
		} catch ( RuntimeException $error ) {
			$this->session_handler->destroy_session_data();
			wc_add_notice( $error->getMessage(), 'error' );
			return null;
		}

		wc_add_notice(
			$this->order_processor->last_error(),
			'error'
		);

		return null;
	}
}
