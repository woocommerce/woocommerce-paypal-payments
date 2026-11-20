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
		$retry_keys_messages = array(
			'INSTRUMENT_DECLINED'   => __( 'Instrument declined.', 'woocommerce-paypal-payments' ),
			'PAYER_ACTION_REQUIRED' => __( 'Payer action required, possibly overcharge.', 'woocommerce-paypal-payments' ),
		);
		$retry_errors        = array_values(
			array_filter(
				array_keys( $retry_keys_messages ),
				function ( string $key ) use ( $error ): bool {
					return $error->has_detail( $key );
				}
			)
		);

		if ( ! $retry_errors ) {
			return null;
		}

		$retry_error_key = $retry_errors[0];
		$retry_message   = $retry_keys_messages[ $retry_error_key ] . ' ' . ( $error->details()[0]->description ?? '' );

		$this->session_handler->increment_insufficient_funding_tries();
		if ( $this->session_handler->insufficient_funding_tries() >= 3 ) {
			$wc_order->update_status( 'failed', $retry_message );

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
			$wc_order->update_status( 'failed', $retry_message );

			return $this->handle_payment_failure(
				null,
				new Exception( __( 'Payment session expired. Please try again.', 'woocommerce-paypal-payments' ) )
			);
		}

		// The payer action or retry is still possible, so keep the order
		// in its current status instead of failing it prematurely - only
		// note the reason and send the customer back to complete it.
		$wc_order->add_order_note( $retry_message );
		$this->session_handler->replace_payer_action_order_id( $session_order->id() );

		return array(
			'result'   => 'success',
			'redirect' => ( $this->paypal_checkout_url_factory )( $session_order->id() ),
		);
	}
}
