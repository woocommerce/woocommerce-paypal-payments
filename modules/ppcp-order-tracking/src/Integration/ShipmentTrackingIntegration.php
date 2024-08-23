<?php
/**
 * The Shipment integration for Shipment Tracking plugin.
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
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_REST_Request;
use WP_REST_Response;
use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;

/**
 * Class ShipmentTrackingIntegration.
 */
class ShipmentTrackingIntegration implements Integration {

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
	 * The Shipment Tracking Integration constructor.
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
			'wp_ajax_wc_shipment_tracking_save_form',
			function() {
				try {
					check_ajax_referer( 'create-tracking-item', 'security', true );

					if ( ! apply_filters( 'woocommerce_paypal_payments_sync_wc_shipment_tracking', true ) ) {
						return;
					}

					$order_id = (int) wc_clean( wp_unslash( $_POST['order_id'] ?? '' ) );
					$wc_order = wc_get_order( $order_id );
					if ( ! is_a( $wc_order, WC_Order::class ) ) {
						return;
					}

					$paypal_order    = ppcp_get_paypal_order( $wc_order );
					$capture_id      = $this->get_paypal_order_transaction_id( $paypal_order );
					$tracking_number = wc_clean( wp_unslash( $_POST['tracking_number'] ?? '' ) );
					$carrier         = wc_clean( wp_unslash( $_POST['tracking_provider'] ?? '' ) );
					$carrier_other   = wc_clean( wp_unslash( $_POST['custom_tracking_provider'] ?? '' ) );
					$carrier         = $carrier ?: $carrier_other ?: '';

					if ( ! $tracking_number || ! is_string( $tracking_number ) || ! $carrier || ! is_string( $carrier ) || ! $capture_id ) {
						return;
					}

					$this->sync_tracking( $order_id, $capture_id, $tracking_number, $carrier );

				} catch ( Exception $exception ) {
					return;
				}
			}
		);

		/**
		 * Support the case when tracking is added via REST.
		 */
		add_filter(
			'woocommerce_rest_prepare_order_shipment_tracking',
			function( WP_REST_Response $response, array $tracking_item, WP_REST_Request $request ): WP_REST_Response {
				try {
					if ( ! apply_filters( 'woocommerce_paypal_payments_sync_wc_shipment_tracking', true ) ) {
						return $response;
					}

					$callback = $request->get_attributes()['callback']['1'] ?? '';
					if ( $callback !== 'create_item' ) {
						return $response;
					}

					$order_id = $tracking_item['order_id'] ?? 0;
					$wc_order = wc_get_order( $order_id );
					if ( ! is_a( $wc_order, WC_Order::class ) ) {
						return $response;
					}

					$paypal_order    = ppcp_get_paypal_order( $wc_order );
					$capture_id      = $this->get_paypal_order_transaction_id( $paypal_order );
					$tracking_number = $tracking_item['tracking_number'] ?? '';
					$carrier         = $tracking_item['tracking_provider'] ?? '';
					$carrier_other   = $tracking_item['custom_tracking_provider'] ?? '';
					$carrier         = $carrier ?: $carrier_other ?: '';

					if ( ! $tracking_number || ! $carrier || ! $capture_id ) {
						return $response;
					}

					$this->sync_tracking( $order_id, $capture_id, $tracking_number, $carrier );

				} catch ( Exception $exception ) {
					return $response;
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
	 * @param string $capture_id The capture ID.
	 * @param string $tracking_number The tracking number.
	 * @param string $carrier The shipment carrier.
	 * @return void
	 */
	protected function sync_tracking(
		int $wc_order_id,
		string $capture_id,
		string $tracking_number,
		string $carrier
	) {
		try {
			$ppcp_shipment = $this->shipment_factory->create_shipment(
				$wc_order_id,
				$capture_id,
				$tracking_number,
				'SHIPPED',
				'OTHER',
				$carrier,
				array()
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
