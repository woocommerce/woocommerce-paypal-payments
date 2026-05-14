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
use LogicException;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\Abilities\Abilities_Registrar;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpointCached;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\PPCP;

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
 * (payment_source.email_address, name, address). The current projection
 * passes the raw entity through after to_array() — operators consuming
 * the ability should apply downstream PII handling appropriate to their
 * context. Documented in the ability description so callers can opt out
 * before exposing the response to LLM contexts.
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

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-paypal-order';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get PayPal order', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the full PayPal order (status, intent, purchase units, payment source, captures, authorizations) for a given PayPal order ID or WooCommerce order ID. The response can include payer PII (email, name, address) — apply downstream handling appropriate to the calling context.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(
					'paypal_order_id' => array(
						'type'        => 'string',
						'description' => __( 'PayPal v2 order ID. Either this or wc_order_id is required.', 'woocommerce-paypal-payments' ),
					),
					'wc_order_id'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'WooCommerce order ID; the PayPal order ID is resolved from order meta. Either this or paypal_order_id is required.', 'woocommerce-paypal-payments' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce' ),
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
	 * @param mixed $input Expected shape: { paypal_order_id?: string, wc_order_id?: int }.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input = is_array( $input ) ? $input : array();

		$identifier = self::extract_identifier( $input );
		if ( $identifier instanceof \WP_Error ) {
			return $identifier;
		}

		$endpoint = self::resolve_endpoint();
		if ( $endpoint instanceof \WP_Error ) {
			return $endpoint;
		}

		try {
			$order = $endpoint->order( $identifier );
		} catch ( Throwable $e ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_order_lookup_failed',
				$e->getMessage(),
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

		return $order->to_array();
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

	/**
	 * Resolve the cached OrderEndpoint service from the plugin container.
	 *
	 * @return OrderEndpointCached|\WP_Error
	 */
	private static function resolve_endpoint() {
		try {
			$service = PPCP::container()->get( self::SERVICE_ID );
		} catch ( LogicException $e ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_not_initialized',
				__( 'WooCommerce PayPal Payments is not initialized; PayPal order endpoint is unavailable.', 'woocommerce-paypal-payments' )
			);
		} catch ( Throwable $e ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_service_unavailable',
				__( 'PayPal order endpoint could not be resolved.', 'woocommerce-paypal-payments' )
			);
		}

		if ( ! $service instanceof OrderEndpointCached ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_service_unavailable',
				__( 'PayPal order endpoint returned an unexpected type.', 'woocommerce-paypal-payments' )
			);
		}

		return $service;
	}
}
