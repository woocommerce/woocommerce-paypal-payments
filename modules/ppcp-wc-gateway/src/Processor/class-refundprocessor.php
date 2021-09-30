<?php
/**
 * Processes refunds started in the WooCommerce environment.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use Psr\Log\LoggerInterface;
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
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * RefundProcessor constructor.
	 *
	 * @param OrderEndpoint    $order_endpoint The order endpoint.
	 * @param PaymentsEndpoint $payments_endpoint The payments endpoint.
	 * @param LoggerInterface  $logger The logger.
	 */
	public function __construct( OrderEndpoint $order_endpoint, PaymentsEndpoint $payments_endpoint, LoggerInterface $logger ) {

		$this->order_endpoint    = $order_endpoint;
		$this->payments_endpoint = $payments_endpoint;
		$this->logger            = $logger;
	}

	/**
	 * Processes a refund.
	 *
	 * @param \WC_Order  $wc_order The WooCommerce order.
	 * @param float|null $amount The refund amount.
	 * @param string     $reason The reason for the refund.
	 *
	 * @return bool
	 *
	 * @phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
	 */
	public function process( \WC_Order $wc_order, float $amount = null, string $reason = '' ) : bool {
		try {
			$order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
			if ( ! $order_id ) {
				throw new RuntimeException( 'PayPal order ID not found in meta.' );
			}

			$order = $this->order_endpoint->order( $order_id );

			$purchase_units = $order->purchase_units();
			if ( ! $purchase_units ) {
				throw new RuntimeException( 'No purchase units.' );
			}

			$payments = $purchase_units[0]->payments();
			if ( ! $payments ) {
				throw new RuntimeException( 'No payments.' );
			}
			$captures = $payments->captures();
			if ( ! $captures ) {
				throw new RuntimeException( 'No capture.' );
			}

			$capture = $captures[0];
			$refund  = new Refund(
				$capture,
				$capture->invoice_id(),
				$reason,
				new Amount(
					new Money( $amount, $wc_order->get_currency() )
				)
			);
			$this->payments_endpoint->refund( $refund );
			return true;
		} catch ( Exception $error ) {
			$this->logger->error( 'Refund failed: ' . $error->getMessage() );
			return false;
		}
	}
}
