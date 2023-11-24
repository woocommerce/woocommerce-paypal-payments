<?php
/**
 * The Shipment integration for ShipStation plugin.
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

/**
 * Class ShipStationIntegration.
 */
class ShipStationIntegration implements Integration {

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
	 * The ShipStationIntegration constructor.
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

		add_action(
			'woocommerce_shipstation_shipnotify',
			/**
			 * Param type for $wc_order can be different.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $wc_order, array $data ) {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_ship_station_tracking', true ) ) {
					return;
				}

				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$order_id        = $wc_order->get_id();
				$transaction_id  = $wc_order->get_transaction_id();
				$tracking_number = $data['tracking_number'] ?? '';
				$carrier         = $data['carrier'] ?? '';

				if ( ! $tracking_number || ! is_string( $tracking_number ) || ! $carrier || ! is_string( $carrier ) || ! $transaction_id ) {
					return;
				}

				try {
					$ppcp_shipment = $this->shipment_factory->create_shipment(
						$order_id,
						$transaction_id,
						$tracking_number,
						'SHIPPED',
						'OTHER',
						$carrier,
						array()
					);

					$tracking_information = $this->endpoint->get_tracking_information( $order_id, $tracking_number );

					$tracking_information
						? $this->endpoint->update_tracking_information( $ppcp_shipment, $order_id )
						: $this->endpoint->add_tracking_information( $ppcp_shipment, $order_id );

				} catch ( Exception $exception ) {
					$this->logger->error( "Couldn't sync tracking information: " . $exception->getMessage() );
				}
			},
			500,
			2
		);
	}
}
