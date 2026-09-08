<?php
/**
 * Get PayPal Order ability definition.
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
 * Registers woocommerce-paypal-payments/get-paypal-order.
 *
 * Looks up a PayPal order (id, intent, status, purchase units with embedded
 * captures/authorizations/refunds, timestamps, links) by PayPal order ID OR
 * WooCommerce order ID. Backed by OrderEndpointCached::order() (Shape 3);
 * cached to amortize repeat lookups in a session.
 *
 * Security: payer PII (top-level `payer`) and per-purchase-unit `shipping`
 * are STRIPPED unless include_payer_pii is true. `payment_source` is stripped
 * defensively — Order::to_array() does not serialize it today, but a future
 * change that did would leak through the denylist gap (pinned by
 * test_project_order_does_not_leak_synthetic_payment_source).
 *
 * @internal
 */
class GetPaypalOrder extends AbstractPpcpAbility implements AbilityDefinition {

	public static function get_name(): string {
		return AbilityNames::GET_PAYPAL_ORDER;
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal order', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the PayPal order (id, intent, status, purchase units with embedded captures / authorizations / refunds, create/update timestamps, links) for a given PayPal order ID or WooCommerce order ID. Payer PII (email, name, address, phone) and per-purchase-unit shipping addresses are stripped by default; pass include_payer_pii: true to opt in when the calling context legitimately needs them.', 'woocommerce-paypal-payments' ),
			'category'            => AbilityNames::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(
					'paypal_order_id'   => array(
						'type'        => 'string',
						'description' => __( 'PayPal v2 order ID (alphanumeric uppercase, up to 64 chars). Either this or wc_order_id is required.', 'woocommerce-paypal-payments' ),
					),
					'wc_order_id'       => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'WooCommerce order ID; the PayPal order ID is resolved from order meta. Either this or paypal_order_id is required.', 'woocommerce-paypal-payments' ),
					),
					'include_payer_pii' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'When true, returns the payer block (email, name, address, phone) and per-purchase-unit shipping addresses. Defaults to false; only opt in when the calling context legitimately needs payer identity.', 'woocommerce-paypal-payments' ),
					),
				),
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
