<?php
/**
 * The Shipment integration for DHL Shipping Germany for WooCommerce plugin.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Integration
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking\Integration;

use Psr\Log\LoggerInterface;
use WC_Order;
use Exception;
use WooCommerce\PayPalCommerce\Compat\Integration;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;

/**
 * Class DhlShipmentIntegration
 */
class DhlShipmentIntegration implements Integration {

	use TransactionIdHandlingTrait;

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
	 * The DhlShipmentIntegration constructor.
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
			'pr_save_dhl_label_tracking',
			function( int $order_id, array $tracking_details ) {
				try {
					$wc_order = wc_get_order( $order_id );
					if ( ! is_a( $wc_order, WC_Order::class ) ) {
						return;
					}

					$paypal_order    = ppcp_get_paypal_order( $wc_order );
					$capture_id      = $this->get_paypal_order_transaction_id( $paypal_order );
					$tracking_number = $tracking_details['tracking_number'];
					$carrier         = $tracking_details['carrier'];

					if ( ! $tracking_number || ! is_string( $tracking_number ) || ! $carrier || ! is_string( $carrier ) || ! $capture_id ) {
						return;
					}

					$ppcp_shipment = $this->shipment_factory->create_shipment(
						$order_id,
						$capture_id,
						$tracking_number,
						'SHIPPED',
						'DE_DHL',
						$carrier,
						array()
					);

					$tracking_information = $this->endpoint->get_tracking_information( $order_id, $tracking_number );

					$tracking_information
						? $this->endpoint->update_tracking_information( $ppcp_shipment, $order_id )
						: $this->endpoint->add_tracking_information( $ppcp_shipment, $order_id );

				} catch ( Exception $exception ) {
					return;
				}
			},
			600,
			2
		);
	}
}
