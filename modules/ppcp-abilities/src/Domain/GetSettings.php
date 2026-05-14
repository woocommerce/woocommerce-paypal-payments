<?php
/**
 * Get Settings ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\PayPalCommerce\Abilities\Abilities_Registrar;
use WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint;

/**
 * Registers the woocommerce-paypal-payments/get-settings ability.
 *
 * Returns every configuration value exposed by the plugin's "Settings" tab
 * — invoice prefix, brand name, soft descriptor, 3DS mode, capture
 * options, landing page, button language, logging, etc. — so an agent can
 * answer "how is checkout currently configured?" in one zero-arg call.
 * Backs onto SettingsRestEndpoint::get_details (Shape 2 — REST delegate).
 *
 * @internal
 */
class GetSettings extends AbstractPpcpAbility implements AbilityDefinition {

	private const REST_ROUTE = '/wc/v3/wc_paypal/settings';

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-settings';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal Payments settings', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the general PayPal Payments settings (brand name, soft descriptor, invoice prefix, 3-D Secure mode, capture options, landing page, button language, logging) currently applied to checkout.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce' ),
			// output_schema deliberately omitted — see SettingsRestEndpoint
			// $field_map (modules/ppcp-settings/src/Endpoint/SettingsRestEndpoint.php
			// lines 47-117) for the canonical key list.
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
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		unset( $input );

		$response = self::delegate_to_rest_controller(
			SettingsRestEndpoint::class,
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
