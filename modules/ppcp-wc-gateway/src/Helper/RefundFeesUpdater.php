<?php
/**
 * The RefundFeesUpdater helper.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WP_Comment;

/**
 * CheckoutHelper class.
 */
class RefundFeesUpdater {

	/**
	 * The Order Endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * RefundFeesUpdater constructor.
	 *
	 * @param OrderEndpoint   $order_endpoint The Order Endpoint.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( OrderEndpoint $order_endpoint, LoggerInterface $logger ) {
		$this->order_endpoint = $order_endpoint;
		$this->logger         = $logger;
	}

	/**
	 * Updates the fees meta for a given order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return void
	 */
	public function update( WC_Order $wc_order ): void {
		$paypal_order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );

		if ( ! $paypal_order_id ) {
			$this->logger->error(
				sprintf( 'Failed to update order paypal refund fees. No PayPal order_id. [wc_order: %s]', $wc_order->get_id() )
			);
			return;
		}

		$this->logger->debug(
			sprintf( 'Updating order paypal refund fees. [wc_order: %s, paypal_order: %s]', $wc_order->get_id(), $paypal_order_id )
		);

		$paypal_order   = $this->order_endpoint->order( $paypal_order_id );
		$purchase_units = $paypal_order->purchase_units();

		$gross_amount_total = 0.0;
		$fee_total          = 0.0;
		$net_amount_total   = 0.0;
		$currency_codes     = array();
		$refunds_ids        = array();

		foreach ( $purchase_units as $purchase_unit ) {
			$payments = $purchase_unit->payments();

			if ( ! $payments ) {
				continue;
			}

			$refunds = $payments->refunds();

			foreach ( $refunds as $refund ) {
				$breakdown     = $refund->seller_payable_breakdown();
				$refunds_ids[] = $refund->id();

				if ( ! $breakdown ) {
					continue;
				}

				$gross_amount = $breakdown->gross_amount();
				if ( $gross_amount ) {
					$gross_amount_total += $gross_amount->value();
					$currency_codes[]    = $gross_amount->currency_code();
				}

				$paypal_fee = $breakdown->paypal_fee();
				if ( $paypal_fee ) {
					$fee_total       += $paypal_fee->value();
					$currency_codes[] = $paypal_fee->currency_code();
				}

				$net_amount = $breakdown->net_amount();
				if ( $net_amount ) {
					$net_amount_total += $net_amount->value();
					$currency_codes[]  = $net_amount->currency_code();
				}
			}
		}

		$currency_codes = array_unique( $currency_codes );

		if ( count( $currency_codes ) > 1 ) {
			// There are multiple different currencies codes in the refunds.

			$this->logger->warning(
				sprintf(
					'Updating order paypal refund fees. Multiple currencies detected. [wc_order: %s, paypal_order: %s, currencies: %s]',
					$wc_order->get_id(),
					$paypal_order_id,
					implode( ',', $currency_codes )
				)
			);

			$wc_order->update_meta_data( PayPalGateway::REFUND_FEES_META_KEY, array() );
			return;
		}

		$currency_code = current( $currency_codes ) ?: '';

		$meta_data = array(
			'gross_amount' => ( new Money( $gross_amount_total, $currency_code ) )->to_array(),
			'paypal_fee'   => ( new Money( $fee_total, $currency_code ) )->to_array(),
			'net_amount'   => ( new Money( $net_amount_total, $currency_code ) )->to_array(),
		);

		$wc_order->update_meta_data( PayPalGateway::REFUND_FEES_META_KEY, $meta_data );
		$wc_order->save();

		$order_notes = $this->get_order_notes( $wc_order );

		foreach ( $refunds_ids as $refund_id ) {
			$has_note = false;
			foreach ( $order_notes as $order_note ) {
				if ( strpos( $order_note->comment_content, $refund_id ) !== false ) {
					$has_note = true;
				}
			}
			if ( ! $has_note ) {
				$wc_order->add_order_note( sprintf( 'PayPal refund ID: %s', $refund_id ) );
			}
		}

		$this->logger->debug(
			sprintf( 'Updated order paypal refund fees. [wc_order: %s, paypal_order: %s]', $wc_order->get_id(), $paypal_order_id )
		);
	}

	/**
	 * Returns all order notes
	 * Based on WC_Order::get_customer_order_notes
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return WP_Comment[]
	 */
	private function get_order_notes( WC_Order $wc_order ): array {
		$notes = array();
		$args  = array(
			'post_id' => $wc_order->get_id(),
		);

		// By default, WooCommerce excludes comments of the comment_type order_note.
		// We need to remove this filter to get the order notes.
		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );

		$comments = get_comments( $args );

		if ( is_array( $comments ) ) {
			foreach ( $comments as $comment ) {
				if ( $comment instanceof WP_Comment ) {
					$notes[] = $comment;
				}
			}
		}

		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );

		return $notes;
	}

}
