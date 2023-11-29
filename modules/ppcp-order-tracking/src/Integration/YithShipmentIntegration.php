<?php
/**
 * The Shipment integration for YITH WooCommerce Order & Shipment Tracking plugin.
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
 * Class YithShipmentIntegration.
 */
class YithShipmentIntegration implements Integration {

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
	 * The YithShipmentIntegration constructor.
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
			'woocommerce_process_shop_order_meta',
			function( int $order_id ) {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_ywot_tracking', true ) ) {
					return;
				}

				$wc_order = wc_get_order( $order_id );
				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$transaction_id = $wc_order->get_transaction_id();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$tracking_number = wc_clean( wp_unslash( $_POST['ywot_tracking_code'] ?? '' ) );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$carrier = wc_clean( wp_unslash( $_POST['ywot_carrier_name'] ?? '' ) );

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
			1
		);
	}
}
