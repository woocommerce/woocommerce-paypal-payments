<?php
/**
 * Get Order Tracking ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use Throwable;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;

/**
 * Registers the woocommerce-paypal-payments/get-order-tracking ability.
 *
 * Lists shipment tracking entries (carrier, tracking number, status) for
 * a WooCommerce order so an agent can answer "what carriers and tracking
 * numbers are on order X?" Backed by
 * OrderTrackingEndpoint::list_tracking_information(int $wc_order_id)
 * (Shape 3 — direct service call).
 *
 * The backing service issues two synchronous remote PayPal calls per
 * invocation (PayPal order lookup to extract the capture id, then a
 * trackers list against PayPal's /v1/shipping/trackers).
 *
 * @internal
 */
class GetOrderTracking extends AbstractPpcpAbility implements AbilityDefinition {

	/**
	 * Container service id for the OrderTrackingEndpoint.
	 *
	 * Cross-referenced at modules/ppcp-order-tracking/services.php line 36.
	 *
	 * @var string
	 */
	private const SERVICE_ID = 'order-tracking.endpoint.controller';

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-order-tracking';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal order tracking', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the shipment tracking entries (carrier, tracking number, status) registered with PayPal for a WooCommerce order. Issues two synchronous PayPal API calls.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(
					'wc_order_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The WooCommerce order ID whose tracking entries should be returned.', 'woocommerce-paypal-payments' ),
					),
				),
				'required'             => array( 'wc_order_id' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
			),
		);
	}

	/**
	 * Execute callback.
	 *
	 * @param mixed $input Expected shape: { wc_order_id: int }.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
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

		$controller = self::resolve_service( self::SERVICE_ID, OrderTrackingEndpoint::class );
		if ( $controller instanceof \WP_Error ) {
			return $controller;
		}

		try {
			$shipments = $controller->list_tracking_information( $wc_order_id );
		} catch ( Throwable $e ) {
			// Don't forward $e->getMessage() to the agent — PayPalApiException
			// appends information_link URLs that can leak internal API
			// structure into LLM contexts. Log full detail server-side.
			error_log( '[ppcp-abilities] get-order-tracking lookup threw ' . get_class( $e ) . ' for wc_order_id=' . $wc_order_id . ': ' . $e->getMessage() );

			return new \WP_Error(
				'woocommerce_paypal_payments_tracking_lookup_failed',
				__( 'PayPal tracking lookup failed; see server log for details.', 'woocommerce-paypal-payments' ),
				array( 'wc_order_id' => $wc_order_id )
			);
		}

		if ( null === $shipments ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_tracking_lookup_failed',
				__( 'PayPal tracking lookup returned an unexpected response.', 'woocommerce-paypal-payments' ),
				array( 'wc_order_id' => $wc_order_id )
			);
		}

		return array(
			'wc_order_id' => $wc_order_id,
			'shipments'   => array_map( array( self::class, 'serialize_shipment' ), $shipments ),
		);
	}

	/**
	 * Serialize a ShipmentInterface to a plain array for the agent payload.
	 *
	 * Delegates to the entity's own to_array() so the wire shape stays in
	 * sync with however ShipmentFactory chooses to represent shipments.
	 * Public so unit tests can assert the serialization shape without
	 * standing up the plugin container.
	 *
	 * @param ShipmentInterface $shipment The shipment entity.
	 * @return array<string, mixed>
	 */
	public static function serialize_shipment( ShipmentInterface $shipment ): array {
		return $shipment->to_array();
	}
}
