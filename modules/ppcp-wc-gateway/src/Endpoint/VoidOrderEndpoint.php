<?php
/**
 * The Void Order endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;

/**
 * Class VoidOrderEndpoint
 */
class VoidOrderEndpoint {

	const ENDPOINT = 'ppc-void-order';

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The Refund Processor.
	 *
	 * @var RefundProcessor
	 */
	private $refund_processor;

	/**
	 * The Request Data Helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * VoidOrderEndpoint constructor.
	 *
	 * @param RequestData     $request_data The Request Data Helper.
	 * @param OrderEndpoint   $order_endpoint The order endpoint.
	 * @param RefundProcessor $refund_processor The Refund Processor.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		RefundProcessor $refund_processor,
		LoggerInterface $logger
	) {
		$this->request_data     = $request_data;
		$this->order_endpoint   = $order_endpoint;
		$this->refund_processor = $refund_processor;
		$this->logger           = $logger;
	}

	/**
	 * Returns the nonce.
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the incoming request.
	 */
	public function handle_request(): void {
		$request = $this->request_data->read_request( self::nonce() );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array(
					'message' => 'Invalid request.',
				)
			);
			return;
		}

		$wc_order_id = (int) $request['wc_order_id'];

		$wc_order = wc_get_order( $wc_order_id );
		if ( ! $wc_order instanceof WC_Order ) {
			wp_send_json_error(
				array(
					'message' => 'WC order not found.',
				)
			);
			return;
		}
		$order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		if ( ! $order_id ) {
			wp_send_json_error(
				array(
					'message' => 'PayPal order ID not found in meta.',
				)
			);
			return;
		}

		try {
			$order = $this->order_endpoint->order( $order_id );

			$this->refund_processor->void( $order );

			$this->make_refunded( $wc_order );
		} catch ( Exception $exception ) {
			wp_send_json_error(
				array(
					'message' => 'Void failed. ' . $exception->getMessage(),
				)
			);
			$this->logger->error( 'Void failed. ' . $exception->getMessage() );
			return;
		}

		wp_send_json_success();
	}

	/**
	 * Returns the list of items for the wc_create_refund data,
	 * making all items refunded (max qty, total, taxes).
	 *
	 * @param WC_Order $wc_order The WC order.
	 */
	protected function refund_items( WC_Order $wc_order ): array {
		$refunded_items = array();
		foreach ( $wc_order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
			// Some methods like get_taxes() are not defined in WC_Order_Item.
			if (
				! $item instanceof WC_Order_Item_Product
				&& ! $item instanceof WC_Order_Item_Fee
				&& ! $item instanceof WC_Order_Item_Shipping
			) {
				continue;
			}

			$taxes      = array();
			$item_taxes = $item->get_taxes();
			/**
			 * The type is not really guaranteed in the code.
			 *
			 *  @psalm-suppress RedundantConditionGivenDocblockType
			 */
			if ( is_array( $item_taxes ) && isset( $item_taxes['total'] ) ) {
				$taxes = $item_taxes['total'];
			}

			$refunded_items[ $item->get_id() ] = array(
				'qty'          => $item->get_type() === 'line_item' ? $item->get_quantity() : 0,
				'refund_total' => $item->get_total(),
				'refund_tax'   => $taxes,
			);
		}
		return $refunded_items;
	}

	/**
	 * Creates a full refund.
	 *
	 * @param WC_Order $wc_order The WC order.
	 */
	private function make_refunded( WC_Order $wc_order ): void {
		wc_create_refund(
			array(
				'amount'         => $wc_order->get_total(),
				'reason'         => __( 'Voided authorization', 'woocommerce-paypal-payments' ),
				'order_id'       => $wc_order->get_id(),
				'line_items'     => $this->refund_items( $wc_order ),
				'refund_payment' => false,
				'restock_items'  => (bool) apply_filters( 'woocommerce_paypal_payments_void_restock_items', true ),
			)
		);

		$wc_order->set_status( 'refunded' );
	}
}

