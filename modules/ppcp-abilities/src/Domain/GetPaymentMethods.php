<?php
/**
 * Get Payment Methods ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\PayPalCommerce\Abilities\Abilities_Registrar;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;

/**
 * Registers the woocommerce-paypal-payments/get-payment-methods ability.
 *
 * Lists every PayPal payment gateway (PayPal, Pay Later, Card Fields/ACDC,
 * Apple Pay, Google Pay, Venmo, Fastlane, APMs) with enabled state,
 * dependency edges, and warning messages so an agent can answer "which
 * payment methods are active and are any of them blocked?" in one zero-arg
 * call. Backs onto PaymentRestEndpoint::get_details (Shape 2 — REST delegate).
 *
 * The response shape is heterogeneous: per-gateway records keyed by
 * gateway id, plus a special `__meta` key, plus top-level convenience
 * flags (paypalShowLogo, cardholderName, fastlaneDisplayWatermark, PUI
 * fields). The output is also passed through the
 * `woocommerce_paypal_payments_payment_methods` filter so third-party
 * extensions can mutate it.
 *
 * @internal
 */
class GetPaymentMethods extends AbstractPpcpAbility implements AbilityDefinition {

	private const REST_ROUTE = '/wc/v3/wc_paypal/payment';

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-payment-methods';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal Payments payment methods', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns every PayPal payment gateway (PayPal, Pay Later, Card Fields/ACDC, Apple Pay, Google Pay, Venmo, Fastlane, APMs) with its enabled state, dependency edges, and any warning messages currently surfaced in the admin UI.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce' ),
			// output_schema deliberately omitted — the heterogeneous shape
			// (gateway map + __meta + flat config keys) is documented by
			// the audit doc; duplicating it here would couple the ability
			// to the filterable output of the REST endpoint.
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
	 * @param mixed $input Optional; ignored.
	 * @return array|\WP_Error The unwrapped payment-methods payload or
	 *                         WP_Error on transport / envelope failure.
	 */
	public static function execute( $input = null ) {
		unset( $input );

		$response = self::delegate_to_rest_controller(
			PaymentRestEndpoint::class,
			'GET',
			self::REST_ROUTE
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$unwrapped = self::unwrap_envelope( $response );

		if ( is_wp_error( $unwrapped ) ) {
			return $unwrapped;
		}

		return is_array( $unwrapped ) ? $unwrapped : array( 'data' => $unwrapped );
	}
}
