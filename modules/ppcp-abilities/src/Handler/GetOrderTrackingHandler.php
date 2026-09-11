<?php
/**
 * Handler for the get-order-tracking ability.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Psr\Log\LoggerInterface;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;

/**
 * Lists shipment tracking entries registered with PayPal for a WooCommerce
 * order. Backed by the injected OrderTrackingEndpoint, which issues two
 * synchronous PayPal API calls per invocation.
 *
 * @internal
 */
class GetOrderTrackingHandler {

	/**
	 * @var OrderTrackingEndpoint
	 */
	private $endpoint;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param OrderTrackingEndpoint $endpoint The backing tracking endpoint.
	 * @param LoggerInterface       $logger   The plugin's PSR-3 logger.
	 */
	public function __construct( OrderTrackingEndpoint $endpoint, LoggerInterface $logger ) {
		$this->endpoint = $endpoint;
		$this->logger   = $logger;
	}

	/**
	 * Execute callback.
	 *
	 * @param mixed $input Expected shape: { wc_order_id: int }.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {
		$input = is_array( $input ) ? $input : array();

		if ( ! isset( $input['wc_order_id'] ) ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_missing_wc_order_id',
				__( 'wc_order_id is required.', 'woocommerce-paypal-payments' )
			);
		}

		$wc_order_id = (int) $input['wc_order_id'];
		if ( $wc_order_id < 1 ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_invalid_input',
				__( 'wc_order_id must be a positive integer.', 'woocommerce-paypal-payments' )
			);
		}

		// Pre-validate the order: the backing endpoint returns [] for an unknown
		// order, indistinguishable from "exists but untracked". Surface a
		// not_found instead.
		if ( ! wc_get_order( $wc_order_id ) instanceof WC_Order ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_not_found',
				__( 'WooCommerce order not found.', 'woocommerce-paypal-payments' ),
				array( 'wc_order_id' => $wc_order_id )
			);
		}

		try {
			$shipments = $this->endpoint->list_tracking_information( $wc_order_id );
		} catch ( Throwable $e ) {
			// Don't forward $e->getMessage() — PayPalApiException leaks
			// information_link URLs into LLM context. Log full detail server-side.
			$this->logger->error( '[ppcp-abilities] get-order-tracking lookup threw ' . get_class( $e ) . ' for wc_order_id=' . $wc_order_id . ': ' . $e->getMessage() );

			return new \WP_Error(
				'woocommerce_paypal_payments_tracking_lookup_failed',
				__( 'PayPal tracking lookup failed; see server log for details.', 'woocommerce-paypal-payments' ),
				array( 'wc_order_id' => $wc_order_id )
			);
		}

		// list_tracking_information() returns null on any non-200 (commonly the
		// 404 "no trackers yet"). Treat as empty; genuine transport failures throw.
		$shipments = null === $shipments ? array() : $shipments;

		return array(
			'wc_order_id' => $wc_order_id,
			'shipments'   => array_map( array( $this, 'serialize_shipment' ), $shipments ),
		);
	}

	/**
	 * Serialize a ShipmentInterface for the agent payload via its own to_array().
	 *
	 * @param ShipmentInterface $shipment The shipment entity.
	 * @return array<string, mixed>
	 */
	private function serialize_shipment( ShipmentInterface $shipment ): array {
		return $shipment->to_array();
	}
}
