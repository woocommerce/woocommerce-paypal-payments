<?php
/**
 * Common operations performed for handling the ACDC order info.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\FraudProcessorResponse;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CardAuthenticationResultFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Trait CreditCardOrderInfoHandlingTrait.
 */
trait CreditCardOrderInfoHandlingTrait {

	/**
	 * Handles the 3DS details.
	 *
	 * Adds the order note with 3DS details.
	 * Adds the order meta with 3DS details.
	 *
	 * @param Order    $order The PayPal order.
	 * @param WC_Order $wc_order The WC order.
	 */
	protected function handle_three_d_secure(
		Order $order,
		WC_Order $wc_order
	): void {

		$payment_source = $order->payment_source();
		if ( ! $payment_source || $payment_source->name() !== 'card' ) {
			return;
		}

		$authentication_result = $payment_source->properties()->authentication_result ?? null;

		if ( $authentication_result ) {
			$card_authentication_result_factory = new CardAuthenticationResultFactory();
			$result                             = $card_authentication_result_factory->from_paypal_response( $authentication_result );

			$three_d_response_order_note_title = __( '3DS Authentication Result', 'woocommerce-paypal-payments' );
			/* translators: %1$s is 3DS order note title, %2$s is 3DS order note result markup */
			$three_d_response_order_note_format        = __( '%1$s %2$s', 'woocommerce-paypal-payments' );
			$three_d_response_order_note_result_format = '<ul class="ppcp_3ds_result">
                                                                <li>%1$s</li>
                                                                <li>%2$s</li>
                                                                <li>%3$s</li>
                                                            </ul>';
			$three_d_response_order_note_result        = sprintf(
				$three_d_response_order_note_result_format,
				/* translators: %s is liability shift */
				sprintf( __( 'Liability Shift: %s', 'woocommerce-paypal-payments' ), esc_html( $result->liability_shift() ) ),
				/* translators: %s is enrollment status */
				sprintf( __( 'Enrollment Status: %s', 'woocommerce-paypal-payments' ), esc_html( $result->enrollment_status() ) ),
				/* translators: %s is authentication status */
				sprintf( __( 'Authentication Status: %s', 'woocommerce-paypal-payments' ), esc_html( $result->authentication_result() ) )
			);
			$three_d_response_order_note = sprintf(
				$three_d_response_order_note_format,
				esc_html( $three_d_response_order_note_title ),
				wp_kses_post( $three_d_response_order_note_result )
			);

			$wc_order->add_order_note( $three_d_response_order_note );

			$wc_order->update_meta_data( PayPalGateway::THREE_D_AUTH_RESULT_META_KEY, $result->to_array() );
			$wc_order->save_meta_data();

			/**
			 * Fired when the 3DS information is added to WC order.
			 */
			do_action( 'woocommerce_paypal_payments_three_d_secure_added', $wc_order, $order );
		}
	}

	/**
	 * Handles the fraud processor response details.
	 *
	 * Adds the order note with the fraud processor response details.
	 * Adds the order meta with the fraud processor response details.
	 *
	 * @param FraudProcessorResponse $fraud The fraud processor response (AVS, CVV ...).
	 * @param Order                  $order The PayPal order.
	 * @param WC_Order               $wc_order The WC order.
	 */
	protected function handle_fraud( FraudProcessorResponse $fraud, Order $order, WC_Order $wc_order ): void {
		$payment_source = $order->payment_source();
		if ( ! $payment_source || $payment_source->name() !== 'card' ) {
			return;
		}

		$fraud_responses  = $fraud->to_array();
		$card_brand       = $payment_source->properties()->brand ?? __( 'N/A', 'woocommerce-paypal-payments' );
		$card_last_digits = $payment_source->properties()->last_digits ?? __( 'N/A', 'woocommerce-paypal-payments' );

		$avs_response_order_note_title = __( 'Address Verification Result', 'woocommerce-paypal-payments' );
		/* translators: %1$s is AVS order note title, %2$s is AVS order note result markup */
		$avs_response_order_note_format        = __( '%1$s %2$s', 'woocommerce-paypal-payments' );
		$avs_response_order_note_result_format = '<ul class="ppcp_avs_result">
                                                                <li>%1$s</li>
                                                                <ul class="ppcp_avs_result_inner">
                                                                    <li>%2$s</li>
                                                                    <li>%3$s</li>
                                                                </ul>
                                                                <li>%4$s</li>
                                                                <li>%5$s</li>
                                                            </ul>';
		$avs_response_order_note_result        = sprintf(
			$avs_response_order_note_result_format,
			/* translators: %s is fraud AVS code */
			sprintf( __( 'AVS: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['avs_code'] ) ),
			/* translators: %s is fraud AVS address match */
			sprintf( __( 'Address Match: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['address_match'] ) ),
			/* translators: %s is fraud AVS postal match */
			sprintf( __( 'Postal Match: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['postal_match'] ) ),
			/* translators: %s is card brand */
			sprintf( __( 'Card Brand: %s', 'woocommerce-paypal-payments' ), esc_html( $card_brand ) ),
			/* translators: %s card last digits */
			sprintf( __( 'Card Last Digits: %s', 'woocommerce-paypal-payments' ), esc_html( $card_last_digits ) )
		);
		$avs_response_order_note = sprintf(
			$avs_response_order_note_format,
			esc_html( $avs_response_order_note_title ),
			wp_kses_post( $avs_response_order_note_result )
		);
		$wc_order->add_order_note( $avs_response_order_note );

		$cvv_response_order_note_format = '<ul class="ppcp_cvv_result"><li>%1$s</li></ul>';
		$cvv_response_order_note        = sprintf(
			$cvv_response_order_note_format,
			/* translators: %s is fraud CVV match */
			sprintf( __( 'CVV2 Match: %s', 'woocommerce-paypal-payments' ), esc_html( $fraud_responses['cvv_match'] ) )
		);
		$wc_order->add_order_note( $cvv_response_order_note );

		$meta_details = array_merge(
			$fraud_responses,
			array(
				'card_brand'       => $card_brand,
				'card_last_digits' => $card_last_digits,
			)
		);
		$wc_order->update_meta_data( PayPalGateway::FRAUD_RESULT_META_KEY, $meta_details );
		$wc_order->save_meta_data();

		/**
		 * Fired when the fraud result information is added to WC order.
		 */
		do_action( 'woocommerce_paypal_payments_fraud_result_added', $wc_order, $order );
	}
}
