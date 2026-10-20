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
use WooCommerce\PayPalCommerce\Abilities\AbilityHandlers;
use WooCommerce\PayPalCommerce\Abilities\AbilityNames;

/**
 * Registers woocommerce-paypal-payments/get-order-tracking.
 *
 * Lists shipment tracking entries for a WooCommerce order. Backed by
 * OrderTrackingEndpoint::list_tracking_information() (Shape 3), which issues
 * two synchronous PayPal API calls per invocation.
 *
 * @internal
 */
class GetOrderTracking extends AbstractPpcpAbility implements AbilityDefinition {

	public static function get_name(): string {
		return AbilityNames::GET_ORDER_TRACKING;
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal order tracking', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the shipment tracking entries (carrier, tracking number, status) registered with PayPal for a WooCommerce order. Issues two synchronous PayPal API calls.', 'woocommerce-paypal-payments' ),
			'category'            => AbilityNames::CATEGORY_SLUG,
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
			'execute_callback'    => AbilityHandlers::callback( self::get_name() ),
			'permission_callback' => AbilityHandlers::permission_callback(),
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
}
