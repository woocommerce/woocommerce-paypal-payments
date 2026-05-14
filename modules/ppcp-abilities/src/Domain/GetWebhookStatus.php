<?php
/**
 * Get Webhook Status ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\Settings\Endpoint\WebhookSettingsEndpoint;

/**
 * Registers the woocommerce-paypal-payments/get-webhook-status ability.
 *
 * Returns the PayPal webhook URL and the list of subscribed event types so
 * an agent can answer "is the webhook configured and which events does it
 * receive?" Pairs with get-last-webhook-event for full webhook health
 * context. Backs onto WebhookSettingsEndpoint::get_webhooks (Shape 2 —
 * REST delegate).
 *
 * The backing controller makes a synchronous remote call to PayPal's
 * Webhooks API. Failures surface as a WP_Error from the envelope unwrap.
 *
 * @internal
 */
class GetWebhookStatus extends AbstractPpcpAbility implements AbilityDefinition {

	private const REST_ROUTE = '/wc/v3/wc_paypal/webhooks';

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-webhook-status';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal Payments webhook status', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the PayPal webhook URL and subscribed event types currently registered for this store. Issues a synchronous PayPal API call.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ),
			// output_schema deliberately omitted — the success envelope's
			// inner shape is { url: string, events: string[] }.
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
			WebhookSettingsEndpoint::class,
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
