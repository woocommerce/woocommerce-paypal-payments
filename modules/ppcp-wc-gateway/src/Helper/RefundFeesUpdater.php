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
				sprintf( 'Update order paypal refund fees. No PayPal order_id. [wc_order: %s]', $wc_order->get_id() )
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

		foreach ( $purchase_units as $purchase_unit ) {
			$payments = $purchase_unit->payments();

			if ( ! $payments ) {
				continue;
			}

			$refunds = $payments->refunds();

			foreach ( $refunds as $refund ) {
				$breakdown = $refund->seller_payable_breakdown();

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
					$gross_amount_total += $net_amount->value();
					$currency_codes[]    = $net_amount->currency_code();
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

		$this->logger->debug(
			sprintf( 'Updated order paypal refund fees. [wc_order: %s, paypal_order: %s]', $wc_order->get_id(), $paypal_order_id )
		);
	}
}
