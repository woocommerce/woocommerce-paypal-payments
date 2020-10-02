<?php
/**
 * Processes refunds started in the WooCommerce environment.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Refund;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class RefundProcessor
 */
class RefundProcessor {

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The payments endpoint.
	 *
	 * @var PaymentsEndpoint
	 */
	private $payments_endpoint;

	/**
	 * RefundProcessor constructor.
	 *
	 * @param OrderEndpoint    $order_endpoint The order endpoint.
	 * @param PaymentsEndpoint $payments_endpoint The payments endpoint.
	 */
	public function __construct( OrderEndpoint $order_endpoint, PaymentsEndpoint $payments_endpoint ) {

		$this->order_endpoint    = $order_endpoint;
		$this->payments_endpoint = $payments_endpoint;
	}

	/**
	 * Processes a refund.
	 *
	 * @param \WC_Order  $wc_order The WooCommerce order.
	 * @param float|null $amount The refund amount.
	 * @param string     $reason The reason for the refund.
	 *
	 * @return bool
	 */
	public function process( \WC_Order $wc_order, float $amount = null, string $reason = '' ) : bool {
		$order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		if ( ! $order_id ) {
			return false;
		}
		try {
			$order = $this->order_endpoint->order( $order_id );
			if ( ! $order ) {
				return false;
			}

			$purchase_units = $order->purchase_units();
			if ( ! $purchase_units ) {
				return false;
			}

			$payments = $purchase_units[0]->payments();
			if ( ! $payments ) {
				return false;
			}
			$captures = $payments->captures();
			if ( ! $captures ) {
				return false;
			}

			$capture = $captures[0];
			$refund  = new Refund(
				$capture,
				$capture->invoice_id(),
				$reason,
				new Amount(
					new Money( $amount, get_woocommerce_currency() )
				)
			);
			return $this->payments_endpoint->refund( $refund );
		} catch ( RuntimeException $error ) {
			return false;
		}
	}
}
