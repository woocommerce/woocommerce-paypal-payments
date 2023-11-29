<?php
/**
 * The Shipment integration for WooCommerce Shipping & Tax plugin.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking\Integration;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\Compat\Integration;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\OrderTracking\TrackingAvailabilityTrait;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class WcShippingTaxIntegration.
 */
class WcShippingTaxIntegration implements Integration {

	use TrackingAvailabilityTrait;

	/**
	 * The shipment factory.
	 *
	 * @var ShipmentFactoryInterface
	 */
	protected $shipment_factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The order tracking endpoint.
	 *
	 * @var OrderTrackingEndpoint
	 */
	protected $endpoint;

	/**
	 * The WcShippingTaxIntegration constructor.
	 *
	 * @param ShipmentFactoryInterface $shipment_factory The shipment factory.
	 * @param LoggerInterface          $logger The logger.
	 * @param OrderTrackingEndpoint    $endpoint The order tracking endpoint.
	 */
	public function __construct(
		ShipmentFactoryInterface $shipment_factory,
		LoggerInterface $logger,
		OrderTrackingEndpoint $endpoint
	) {
		$this->shipment_factory = $shipment_factory;
		$this->logger           = $logger;
		$this->endpoint         = $endpoint;
	}

	/**
	 * {@inheritDoc}
	 */
	public function integrate(): void {

		add_filter(
			'rest_post_dispatch',
			function( WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_HTTP_Response {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_wc_shipping_tax', true ) ) {
					return $response;
				}

				$params   = $request->get_params();
				$order_id = (int) ( $params['order_id'] ?? 0 );
				$label_id = (int) ( $params['label_ids'] ?? 0 );

				if ( ! $order_id || "/wc/v1/connect/label/{$order_id}/{$label_id}" !== $request->get_route() ) {
					return $response;
				}

				$data   = $response->get_data() ?? array();
				$labels = $data['labels'] ?? array();

				foreach ( $labels as $label ) {
					$tracking_number = $label['tracking'] ?? '';
					if ( ! $tracking_number ) {
						continue;
					}

					$wc_order = wc_get_order( $order_id );
					if ( ! is_a( $wc_order, WC_Order::class ) ) {
						continue;
					}

					$transaction_id = $wc_order->get_transaction_id();
					$carrier        = $label['carrier_id'] ?? $label['service_name'] ?? '';
					$items          = array_map( 'intval', $label['product_ids'] ?? array() );

					if ( ! $carrier || ! $transaction_id ) {
						continue;
					}

					$this->sync_tracking( $order_id, $transaction_id, $tracking_number, $carrier, $items );
				}

				return $response;
			},
			10,
			3
		);

	}

	/**
	 * Syncs (add | update) the PayPal tracking with given info.
	 *
	 * @param int    $wc_order_id The WC order ID.
	 * @param string $transaction_id The transaction ID.
	 * @param string $tracking_number The tracking number.
	 * @param string $carrier The shipment carrier.
	 * @param int[]  $items The list of line items IDs.
	 * @return void
	 */
	protected function sync_tracking(
		int $wc_order_id,
		string $transaction_id,
		string $tracking_number,
		string $carrier,
		array $items
	) {
		try {
			$ppcp_shipment = $this->shipment_factory->create_shipment(
				$wc_order_id,
				$transaction_id,
				$tracking_number,
				'SHIPPED',
				'OTHER',
				$carrier,
				$items
			);

			$tracking_information = $this->endpoint->get_tracking_information( $wc_order_id, $tracking_number );

			$tracking_information
				? $this->endpoint->update_tracking_information( $ppcp_shipment, $wc_order_id )
				: $this->endpoint->add_tracking_information( $ppcp_shipment, $wc_order_id );

		} catch ( Exception $exception ) {
			$this->logger->error( "Couldn't sync tracking information: " . $exception->getMessage() );
		}
	}
}
