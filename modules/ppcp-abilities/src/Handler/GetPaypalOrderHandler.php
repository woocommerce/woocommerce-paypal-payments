<?php
/**
 * Handler for the get-paypal-order ability.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Psr\Log\LoggerInterface;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpointCached;

/**
 * Looks up a PayPal order by PayPal order ID or WooCommerce order ID.
 *
 * Security: payer PII (top-level `payer`) and per-purchase-unit `shipping`
 * are STRIPPED unless include_payer_pii is true AND the site permits it via
 * `woocommerce_paypal_payments_abilities_allow_payer_pii` (default true).
 * `payment_source` is stripped defensively — Order::to_array() does not
 * serialize it today, but a future change that did would leak through the
 * denylist gap.
 *
 * @internal
 */
class GetPaypalOrderHandler {

	/**
	 * Constraining the input blocks path-traversal payloads (e.g.
	 * "ID/../refunds") from reaching OrderEndpoint::order(), which concatenates
	 * the id without rawurlencode().
	 */
	private const PAYPAL_ORDER_ID_PATTERN = '/^[A-Z0-9]{1,64}$/';

	private const REDACTED_TOP_LEVEL_KEYS = array( 'payer', 'payment_source' );

	private OrderEndpointCached $endpoint;

	private LoggerInterface $logger;

	public function __construct( OrderEndpointCached $endpoint, LoggerInterface $logger ) {
		$this->endpoint = $endpoint;
		$this->logger   = $logger;
	}

	/**
	 * Accepts EITHER paypal_order_id OR wc_order_id; with wc_order_id the
	 * endpoint resolves the PayPal id from order meta.
	 *
	 * @param mixed $input Expected shape: { paypal_order_id?: string, wc_order_id?: int, include_payer_pii?: bool }.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {
		$input = is_array( $input ) ? $input : array();

		$identifier = $this->extract_identifier( $input );
		if ( $identifier instanceof \WP_Error ) {
			return $identifier;
		}

		try {
			$order = $this->endpoint->order( $identifier );
		} catch ( Throwable $e ) {
			// Don't forward $e->getMessage() — PayPalApiException leaks
			// information_link URLs into LLM context. Log full detail server-side.
			$this->logger->error( '[ppcp-abilities] get-paypal-order lookup threw ' . get_class( $e ) . ': ' . $e->getMessage() );

			return new \WP_Error(
				'woocommerce_paypal_payments_order_lookup_failed',
				__( 'PayPal order lookup failed; see server log for details.', 'woocommerce-paypal-payments' ),
				array(
					'identifier' => is_object( $identifier ) ? get_class( $identifier ) : $identifier,
				)
			);
		}

		return $this->project_order( $order->to_array(), (bool) ( $input['include_payer_pii'] ?? false ) );
	}

	private function project_order( array $payload, bool $include_payer_pii ): array {
		if ( $include_payer_pii && $this->site_allows_payer_pii() ) {
			return $payload;
		}

		if ( $include_payer_pii ) {
			$this->logger->info( '[ppcp-abilities] get-paypal-order: include_payer_pii was requested but the site denies payer PII; returning the redacted payload.' );
		}

		foreach ( self::REDACTED_TOP_LEVEL_KEYS as $key ) {
			unset( $payload[ $key ] );
		}

		if ( isset( $payload['purchase_units'] ) && is_array( $payload['purchase_units'] ) ) {
			foreach ( $payload['purchase_units'] as $i => $unit ) {
				if ( is_array( $unit ) && isset( $unit['shipping'] ) ) {
					unset( $payload['purchase_units'][ $i ]['shipping'] );
				}
			}
		}

		return $payload;
	}

	private function site_allows_payer_pii(): bool {
		/**
		 * Filters whether this site permits PayPal Payments abilities to return
		 * payer PII at all.
		 *
		 * The get-paypal-order ability's `include_payer_pii` input can only
		 * narrow within this: when this returns false, payer PII is stripped
		 * regardless of the input.
		 *
		 * @since 4.1.0
		 *
		 * @param bool $allowed Whether payer PII may be returned. Default true.
		 */
		return (bool) apply_filters( 'woocommerce_paypal_payments_abilities_allow_payer_pii', true );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return string|WC_Order|\WP_Error
	 */
	private function extract_identifier( array $input ) {
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
