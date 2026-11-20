<?php
/**
 * Sends the buyer back to PayPal when a capture fails with a recoverable error.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;

/**
 * Buyers approve the order on PayPal, so a new approval can resolve a declined or
 * interrupted capture. The using class must also use ProcessPaymentTrait and provide
 * $session_handler and $paypal_checkout_url_factory.
 */
trait CaptureRetryTrait {
	/**
	 * Returns the process_payment() result, or null when another approval cannot
	 * resolve the error.
	 */
	protected function handle_capture_retry( WC_Order $wc_order, PayPalApiException $error ): ?array {
		$messages_by_detail_key = array(
			'INSTRUMENT_DECLINED'   => __( 'Instrument declined.', 'woocommerce-paypal-payments' ),
			'PAYER_ACTION_REQUIRED' => __( 'Payer action required, possibly overcharge.', 'woocommerce-paypal-payments' ),
		);

		$failure_reason = null;
		foreach ( $messages_by_detail_key as $key => $message ) {
			if ( $error->has_detail( $key ) ) {
				$failure_reason = $message . ' ' . ( $error->details()[0]->description ?? '' );
				break;
			}
		}

		if ( null === $failure_reason ) {
			return null;
		}

		$this->session_handler->increment_insufficient_funding_tries();
		if ( $this->session_handler->insufficient_funding_tries() >= 3 ) {
			$wc_order->update_status( 'failed', $failure_reason );

			return $this->handle_payment_failure(
				null,
				new Exception(
					__( 'Please use a different payment method.', 'woocommerce-paypal-payments' ),
					$error->getCode(),
					$error
				)
			);
		}

		$session_order = $this->session_handler->order();
		if ( ! ( $session_order instanceof Order ) ) {
			$wc_order->update_status( 'failed', $failure_reason );

			return $this->handle_payment_failure(
				null,
				new Exception( __( 'Payment session expired. Please try again.', 'woocommerce-paypal-payments' ) )
			);
		}

		// Leave the order open for another PayPal approval; the reason only goes into an order note.
		$wc_order->add_order_note( $failure_reason );
		$this->session_handler->replace_payer_action_order_id( $session_order->id() );

		return array(
			'result'   => 'success',
			'redirect' => ( $this->paypal_checkout_url_factory )( $session_order->id() ),
		);
	}
}
