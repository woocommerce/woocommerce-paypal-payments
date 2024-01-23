<?php
/**
 * Common operations performed for handling the 3DS authentication.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CardAuthenticationResultFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Trait ThreeDSecureHandlingTrait.
 */
trait ThreeDSecureHandlingTrait {

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
		if ( ! $payment_source ) {
			return;
		}

		$authentication_result = $payment_source->properties()->authentication_result ?? null;
		$card_brand            = $payment_source->properties()->brand ?? __( 'N/A', 'woocommerce-paypal-payments' );

		if ( $authentication_result ) {
			$card_authentication_result_factory = new CardAuthenticationResultFactory();
			$result                             = $card_authentication_result_factory->from_paypal_response( $authentication_result );

			$three_d_response_order_note_title = __( '3DS authentication result', 'woocommerce-paypal-payments' );
			/* translators: %1$s is 3DS order note title, %2$s is 3DS order note result markup */
			$three_d_response_order_note_format        = __( '%1$s %2$s', 'woocommerce-paypal-payments' );
			$three_d_response_order_note_result_format = '<ul class="ppcp_3ds_result">
                                                                <li>%1$s</li>
                                                                <li>%2$s</li>
                                                                <li>%3$s</li>
                                                                <li>%4$s</li>
                                                            </ul>';
			$three_d_response_order_note_result        = sprintf(
				$three_d_response_order_note_result_format,
				/* translators: %s is liability shift */
				sprintf( __( 'Liability Shift: %s', 'woocommerce-paypal-payments' ), esc_html( $result->liability_shift() ) ),
				/* translators: %s is enrollment status */
				sprintf( __( 'Enrollment Status: %s', 'woocommerce-paypal-payments' ), esc_html( $result->enrollment_status() ) ),
				/* translators: %s is authentication status */
				sprintf( __( 'Authentication Status: %s', 'woocommerce-paypal-payments' ), esc_html( $result->authentication_result() ) ),
				/* translators: %s is card brand */
				sprintf( __( 'Card Brand: %s', 'woocommerce-paypal-payments' ), esc_html( $card_brand ) )
			);
			$three_d_response_order_note = sprintf(
				$three_d_response_order_note_format,
				esc_html( $three_d_response_order_note_title ),
				wp_kses_post( $three_d_response_order_note_result )
			);

			$wc_order->add_order_note( $three_d_response_order_note );

			$meta_details = array_merge( $result->to_array(), array( 'card_brand' => $card_brand ) );
			$wc_order->update_meta_data( PayPalGateway::THREE_D_AUTH_RESULT_META_KEY, $meta_details );
			$wc_order->save_meta_data();

			/**
			 * Fired when the 3DS information is added to WC order.
			 */
			do_action( 'woocommerce_paypal_payments_thee_d_secure_added', $wc_order, $order );
		}
	}
}
