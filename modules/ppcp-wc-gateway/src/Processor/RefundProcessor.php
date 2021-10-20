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
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Refund;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class RefundProcessor
 */
class RefundProcessor {

	private const REFUND_MODE_REFUND  = 'refund';
	private const REFUND_MODE_VOID    = 'void';
	private const REFUND_MODE_UNKNOWN = 'unknown';

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

			$this->logger->debug(
				sprintf(
					'Trying to refund/void order %1$s, payments: %2$s.',
					$order->id(),
					wp_json_encode( $payments->to_array() )
				)
			);

			$mode = $this->determine_refund_mode( $payments );

			switch ( $mode ) {
				case self::REFUND_MODE_REFUND:
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
					break;
				case self::REFUND_MODE_VOID:
					$voidable_authorizations = array_filter(
						$payments->authorizations(),
						function ( Authorization $authorization ): bool {
							return $authorization->is_voidable();
						}
					);
					if ( ! $voidable_authorizations ) {
						throw new RuntimeException( 'No voidable authorizations.' );
					}

					foreach ( $voidable_authorizations as $authorization ) {
						$this->payments_endpoint->void( $authorization );
					}

					$wc_order->set_status( 'refunded' );
					$wc_order->save();

					break;
				default:
					throw new RuntimeException( 'Nothing to refund/void.' );
			}

			return true;
		} catch ( Exception $error ) {
			$this->logger->error( 'Refund failed: ' . $error->getMessage() );
			return false;
		}
	}

	/**
	 * Determines the refunding mode.
	 *
	 * @param Payments $payments The order payments state.
	 *
	 * @return string One of the REFUND_MODE_ constants.
	 */
	private function determine_refund_mode( Payments $payments ): string {
		$authorizations = $payments->authorizations();
		if ( $authorizations ) {
			foreach ( $authorizations as $authorization ) {
				if ( $authorization->is_voidable() ) {
					return self::REFUND_MODE_VOID;
				}
			}
		}

		if ( $payments->captures() ) {
			return self::REFUND_MODE_REFUND;
		}

		return self::REFUND_MODE_UNKNOWN;
	}
}
