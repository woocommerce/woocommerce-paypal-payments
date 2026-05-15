<?php
/**
 * Get Connection Status ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;

/**
 * Registers the woocommerce-paypal-payments/get-connection-status ability.
 *
 * Reference ability for the migration: zero-arg, read-only, answers the
 * highest-leverage question an agent has about a PayPal Payments install
 * ("is this store connected to PayPal and as which account?") in a single
 * call. Backs onto the CommonRestEndpoint::get_merchant_details route,
 * unwraps the plugin's envelope, and STRIPS the API credentials
 * (clientId, clientSecret) before returning — those fields are useful to
 * the admin UI but never to an agent.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active. The
 *           AbilitiesRegistrar short-circuits before referencing this
 *           class on earlier WC versions; PHP's lazy autoload means the
 *           unresolved AbilityDefinition interface FQN never reaches the
 *           parser there.
 */
class GetConnectionStatus extends AbstractPpcpAbility implements AbilityDefinition {

	/**
	 * Backing REST route for the merchant payload.
	 *
	 * @var string
	 */
	private const REST_ROUTE = '/wc/v3/wc_paypal/common/merchant';

	/**
	 * Fields the projection MUST drop before returning to the agent.
	 *
	 * The plugin's $merchant_info_map at
	 * modules/ppcp-settings/src/Endpoint/CommonRestEndpoint.php line 78
	 * exposes both clientId and clientSecret — the OAuth API credentials
	 * the plugin uses to talk to PayPal. They are admin-only by intent;
	 * surfacing them through an ability would let an agent log them
	 * verbatim. The PayPal merchant id (`id`) and email stay because
	 * agents legitimately need them to reason about the account.
	 *
	 * @var array<int, string>
	 */
	private const REDACTED_FIELDS = array( 'clientId', 'clientSecret' );

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-connection-status';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal Payments connection status', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the merchant PayPal connection state (connected, sandbox, merchant ID, email, seller type) for the current store. API credentials are intentionally redacted.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ),
			// output_schema deliberately omitted — the merchant payload's
			// shape is defined by the plugin's $merchant_info_map and
			// duplicating it here would couple the ability to any future
			// settings-data refactor. The projection method documents
			// the contract instead.
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
	 * Delegates to CommonRestEndpoint::get_merchant_details (Shape 2 — REST
	 * delegate) and projects the response into the agent-facing shape via
	 * {@see self::project_merchant_payload()}.
	 *
	 * @param mixed $input Optional; this ability accepts no input but the
	 *                     parameter is kept to match the Abilities API
	 *                     execute_callback signature for forward
	 *                     compatibility.
	 * @return array|\WP_Error Agent-facing connection-status payload or
	 *                         WP_Error on remote/transport failure.
	 */
	public static function execute( $input = null ) {
		unset( $input );

		$response = self::delegate_to_rest_controller(
			CommonRestEndpoint::class,
			'GET',
			self::REST_ROUTE
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! is_array( $response ) ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_unexpected_response',
				__( 'Unexpected response shape from the merchant endpoint.', 'woocommerce-paypal-payments' )
			);
		}

		// Reuse the shared success=false handler so message/details redaction
		// stays consistent with the other Shape-2 abilities. CommonRestEndpoint
		// puts merchant/features at the envelope top level alongside `data`,
		// so we can't go through unwrap_envelope() (which would extract `data`
		// and discard those keys). envelope_error_or_null() gives us just the
		// failure-branch behaviour.
		$envelope_error = self::envelope_error_or_null( $response );
		if ( $envelope_error instanceof \WP_Error ) {
			return $envelope_error;
		}

		return self::project_merchant_payload( $response );
	}

	/**
	 * Project the CommonRestEndpoint success-path response into the
	 * agent-facing payload.
	 *
	 * The endpoint returns (on success):
	 *   {
	 *     success: true,
	 *     data:    [],
	 *     merchant: { isConnected, isSandbox, id, email, sellerType, clientId, clientSecret, isSendOnlyCountry },
	 *     features: [...]   // only when connected
	 *   }
	 *
	 * The `success=false` branch is handled by
	 * {@see AbstractPpcpAbility::unwrap_envelope()} before this projection
	 * is called. The agent surface is the merchant subobject (with API
	 * credentials stripped) plus the optional features list.
	 *
	 * Public so unit tests can assert the redaction behaviour without
	 * standing up a real REST server.
	 *
	 * @param array $payload Decoded REST response array (success branch).
	 * @return array Agent-facing payload.
	 */
	public static function project_merchant_payload( array $payload ): array {
		$merchant = isset( $payload['merchant'] ) && is_array( $payload['merchant'] )
			? $payload['merchant']
			: array();

		foreach ( self::REDACTED_FIELDS as $field ) {
			unset( $merchant[ $field ] );
		}

		$result = array(
			'merchant' => $merchant,
		);

		if ( isset( $payload['features'] ) ) {
			$result['features'] = $payload['features'];
		}

		return $result;
	}
}
