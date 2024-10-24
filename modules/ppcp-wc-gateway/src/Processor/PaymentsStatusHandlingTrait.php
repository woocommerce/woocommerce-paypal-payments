<?php
/**
 * Common operations performed after payment authorization/capture.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Trait PaymentsStatusHandlingTrait.
 */
trait PaymentsStatusHandlingTrait {

	/**
	 * Changes status of a newly created order, based on the capture/authorization.
	 *
	 * @param Order    $order The PayPal order.
	 * @param WC_Order $wc_order The WC order.
	 *
	 * @throws RuntimeException If payment denied.
	 */
	protected function handle_new_order_status(
		Order $order,
		WC_Order $wc_order
	): void {
		if ( $order->intent() === 'CAPTURE' ) {
			$purchase_units = $order->purchase_units();

			if ( ! empty( $purchase_units ) && isset( $purchase_units[0] ) ) {
				$payments = $purchase_units[0]->payments();

				if ( $payments && ! empty( $payments->captures() ) ) {
					$this->handle_capture_status( $payments->captures()[0], $wc_order );
				}
			}
		} elseif ( $order->intent() === 'AUTHORIZE' ) {
			$purchase_units = $order->purchase_units();

			if ( ! empty( $purchase_units ) && isset( $purchase_units[0] ) ) {
				$payments = $purchase_units[0]->payments();

				if ( $payments && ! empty( $payments->authorizations() ) ) {
					$this->handle_authorization_status( $payments->authorizations()[0], $wc_order );
				}
			}
		}
	}

	/**
	 * Changes the order status, based on the capture.
	 *
	 * @param Capture  $capture The capture.
	 * @param WC_Order $wc_order The WC order.
	 *
	 * @throws RuntimeException If payment denied.
	 */
	protected function handle_capture_status(
		Capture $capture,
		WC_Order $wc_order
	): void {
		$status = $capture->status();

		$details = $status->details();
		if ( $details ) {
			$this->add_status_details_note( $wc_order, $status->name(), $details->text() );
		}

		switch ( $status->name() ) {
			case CaptureStatus::COMPLETED:
				$wc_order->payment_complete();
				/**
				 * Fired when PayPal order is captured.
				 */
				do_action( 'woocommerce_paypal_payments_order_captured', $wc_order, $capture );
				break;
			// It is checked in the capture endpoint already, but there are other ways to capture,
			// such as when paid via saved card.
			case CaptureStatus::DECLINED:
				$wc_order->update_status(
					'failed',
					__( 'Could not capture the payment.', 'woocommerce-paypal-payments' )
				);
				throw new RuntimeException( __( 'Payment provider declined the payment, please use a different payment method.', 'woocommerce-paypal-payments' ) );
			case CaptureStatus::PENDING:
			case CaptureStatus::FAILED:
				$wc_order->update_status(
					'on-hold',
					__( 'Awaiting payment.', 'woocommerce-paypal-payments' )
				);
				break;
		}
	}

	/**
	 * Changes the order status, based on the authorization.
	 *
	 * @param Authorization $authorization The authorization.
	 * @param WC_Order      $wc_order The WC order.
	 *
	 * @throws RuntimeException If payment denied.
	 */
	protected function handle_authorization_status(
		Authorization $authorization,
		WC_Order $wc_order
	): void {
		$status = $authorization->status();

		$details = $status->details();
		if ( $details ) {
			$this->add_status_details_note( $wc_order, $status->name(), $details->text() );
		}

		switch ( $status->name() ) {
			case AuthorizationStatus::CREATED:
			case AuthorizationStatus::PENDING:
				$wc_order->update_status(
					apply_filters( 'ppcp_authorized_order_status', 'on-hold' ),
					__( 'Awaiting payment.', 'woocommerce-paypal-payments' )
				);
				/**
				 * Fired when PayPal order is authorized.
				 */
				do_action( 'woocommerce_paypal_payments_order_authorized', $wc_order, $authorization );
				break;
			case AuthorizationStatus::DENIED:
				$wc_order->update_status(
					'failed',
					__( 'Could not get the payment authorization.', 'woocommerce-paypal-payments' )
				);
				throw new RuntimeException( __( 'Payment provider declined the payment, please use a different payment method.', 'woocommerce-paypal-payments' ) );
		}
	}

	/**
	 * Adds the order note with status details.
	 *
	 * @param WC_Order $wc_order The WC order to which the note will be added.
	 * @param string   $status The status name.
	 * @param string   $reason The status reason.
	 */
	protected function add_status_details_note(
		WC_Order $wc_order,
		string $status,
		string $reason
	): void {
		$wc_order->add_order_note(
			sprintf(
				/* translators: %1$s - PENDING, DENIED, ... %2$s - text explaining the reason, ... */
				__( 'PayPal order payment is set to %1$s status, details: %2$s', 'woocommerce-paypal-payments' ),
				$status,
				$reason
			)
		);
		$wc_order->save();
	}
}
