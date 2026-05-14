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
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpointCached;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;

/**
 * Registers the woocommerce-paypal-payments/get-paypal-order ability.
 *
 * Looks up the full PayPal order (status, intent, purchase_units,
 * payment_source, captures, authorizations) for a given PayPal order id
 * OR a WooCommerce order id (which is resolved to the PayPal id via the
 * order's meta) so an agent can answer "what's the PayPal-side state of
 * order X — captured? authorized only? declined?" for support and
 * reconciliation. Backed by OrderEndpointCached::order() (Shape 3 —
 * direct service call); the cached endpoint is preferred over the
 * non-cached one to amortize cost when the same ability is invoked
 * multiple times in a session.
 *
 * The PayPal order response can include payer PII
 * (payment_source.email_address, name, address, phone, birth_date,
 * tax_info) plus shipping addresses on every purchase unit. PII is
 * STRIPPED by default; callers that genuinely need payer identity must
 * opt in by passing `include_payer_pii: true`.
 *
 * @internal
 */
class GetPaypalOrder extends AbstractPpcpAbility implements AbilityDefinition {

	/**
	 * Container service id for the cached OrderEndpoint.
	 *
	 * Cross-referenced at modules/ppcp-api-client/services.php line 272.
	 *
	 * @var string
	 */
	private const SERVICE_ID = 'api.endpoint.order.cached';

	/**
	 * Format a valid PayPal v2 order ID must match.
	 *
	 * PayPal v2 order IDs are alphanumeric uppercase tokens (e.g.
	 * "8XR43025NW123456A"). Constraining the input prevents path
	 * traversal-style payloads (e.g. "ORDERID/../refunds") from being
	 * interpolated into the API URL by OrderEndpoint::order(), which
	 * concatenates the id without rawurlencode().
	 *
	 * @var string
	 */
	private const PAYPAL_ORDER_ID_PATTERN = '/^[A-Z0-9]{1,64}$/';

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-paypal-order';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal order', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the full PayPal order (status, intent, purchase units, payment source, captures, authorizations) for a given PayPal order ID or WooCommerce order ID. Payer PII (email, name, address, phone) is stripped by default; pass include_payer_pii: true to opt in when the calling context legitimately needs it.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
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
	 * Accepts EITHER paypal_order_id OR wc_order_id (exactly one must be
	 * present). When wc_order_id is supplied, the PayPal order endpoint
	 * resolves the PayPal id from order meta internally.
	 *
	 * @param mixed $input Expected shape: { paypal_order_id?: string, wc_order_id?: int, include_payer_pii?: bool }.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input = is_array( $input ) ? $input : array();

		$identifier = self::extract_identifier( $input );
		if ( $identifier instanceof \WP_Error ) {
			return $identifier;
		}

		$endpoint = self::resolve_service( self::SERVICE_ID, OrderEndpointCached::class );
		if ( $endpoint instanceof \WP_Error ) {
			return $endpoint;
		}

		try {
			$order = $endpoint->order( $identifier );
		} catch ( Throwable $e ) {
			// Don't forward $e->getMessage() to the agent — PayPalApiException
			// appends information_link URLs from PayPal's error body that can
			// leak internal API structure into LLM contexts. Log full detail
			// server-side instead.
			error_log( '[ppcp-abilities] get-paypal-order lookup threw: ' . $e->getMessage() );

			return new \WP_Error(
				'woocommerce_paypal_payments_order_lookup_failed',
				__( 'PayPal order lookup failed; see server log for details.', 'woocommerce-paypal-payments' ),
				array(
					'identifier' => is_object( $identifier ) ? get_class( $identifier ) : $identifier,
				)
			);
		}

		if ( ! $order instanceof Order ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_unexpected_response',
				__( 'PayPal order lookup returned an unexpected response shape.', 'woocommerce-paypal-payments' )
			);
		}

		return self::project_order( $order->to_array(), (bool) ( $input['include_payer_pii'] ?? false ) );
	}

	/**
	 * Project the PayPal Order entity payload into the agent-facing shape.
	 *
	 * When `$include_payer_pii` is false (default), the payer block and any
	 * per-purchase-unit shipping addresses are removed before returning.
	 * Public so unit tests can assert the redaction behavior without
	 * standing up the plugin container.
	 *
	 * @param array $payload           Decoded PayPal Order payload.
	 * @param bool  $include_payer_pii When true, the payer + shipping fields are passed through.
	 * @return array
	 */
	public static function project_order( array $payload, bool $include_payer_pii ): array {
		if ( $include_payer_pii ) {
			return $payload;
		}

		unset( $payload['payer'] );

		if ( isset( $payload['purchase_units'] ) && is_array( $payload['purchase_units'] ) ) {
			foreach ( $payload['purchase_units'] as $i => $unit ) {
				if ( is_array( $unit ) && isset( $unit['shipping'] ) ) {
					unset( $payload['purchase_units'][ $i ]['shipping'] );
				}
			}
		}

		return $payload;
	}

	/**
	 * Extract the identifier the OrderEndpoint expects from the ability input.
	 *
	 * Returns a string PayPal order id, a WC_Order, or a WP_Error when
	 * neither identifier resolves.
	 *
	 * @param array<string, mixed> $input
	 * @return string|WC_Order|\WP_Error
	 */
	private static function extract_identifier( array $input ) {
		$paypal_order_id = isset( $input['paypal_order_id'] ) ? (string) $input['paypal_order_id'] : '';
		$wc_order_id     = isset( $input['wc_order_id'] ) ? (int) $input['wc_order_id'] : 0;

		if ( '' !== $paypal_order_id ) {
			if ( ! preg_match( self::PAYPAL_ORDER_ID_PATTERN, $paypal_order_id ) ) {
				return new \WP_Error(
					'woocommerce_paypal_payments_invalid_input',
					__( 'paypal_order_id must be an alphanumeric uppercase PayPal order ID (1-64 chars).', 'woocommerce-paypal-payments' )
				);
			}

			return $paypal_order_id;
		}

		if ( $wc_order_id < 1 ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_missing_identifier',
				__( 'Either paypal_order_id or wc_order_id is required.', 'woocommerce-paypal-payments' )
			);
		}

		$wc_order = wc_get_order( $wc_order_id );
		if ( ! $wc_order instanceof WC_Order ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_not_found',
				__( 'WooCommerce order not found.', 'woocommerce-paypal-payments' ),
				array( 'wc_order_id' => $wc_order_id )
			);
		}

		return $wc_order;
	}
}
