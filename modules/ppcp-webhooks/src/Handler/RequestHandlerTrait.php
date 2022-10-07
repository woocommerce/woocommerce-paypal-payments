<?php
/**
 * Trait which helps to handle the request.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use stdClass;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

trait RequestHandlerTrait {

	/**
	 * Get available custom ids from the given request
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return array
	 */
	protected function get_custom_ids_from_request( WP_REST_Request $request ): array {
		return array_filter(
			array_map(
				static function ( array $purchase_unit ): string {
					return isset( $purchase_unit['custom_id'] ) ?
						(string) $purchase_unit['custom_id'] : '';
				},
				$request['resource'] !== null && isset( $request['resource']['purchase_units'] ) ?
					(array) $request['resource']['purchase_units'] : array()
			),
			static function ( string $order_id ): bool {
				return ! empty( $order_id );
			}
		);
	}

	/**
	 * Get WC orders from the given custom ids.
	 *
	 * @param array $custom_ids The custom ids.
	 * @return WC_Order[]
	 */
	protected function get_wc_orders_from_custom_ids( array $custom_ids ): array {
		$order_ids = array_map(
			array(
				$this,
				'sanitize_custom_id',
			),
			$custom_ids
		);
		$args      = array(
			'post__in' => $order_ids,
			'limit'    => -1,
		);

		$orders = wc_get_orders( $args );
		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Return and log response for no custom ids found in request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param array           $response The response.
	 * @return WP_REST_Response
	 */
	protected function no_custom_ids_from_request( WP_REST_Request $request, array $response ): WP_REST_Response {
		$message = sprintf(
		// translators: %s is the PayPal webhook Id.
			__( 'No order for webhook event %s was found.', 'woocommerce-paypal-payments' ),
			$request['id'] !== null && isset( $request['id'] ) ? $request['id'] : ''
		);

		return $this->log_and_return_response( $message, $response );
	}

	/**
	 * Return and log response for no WC orders found in response.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param array           $response The response.
	 * @return WP_REST_Response
	 */
	protected function no_wc_orders_from_custom_ids( WP_REST_Request $request, array $response ): WP_REST_Response {
		$message = sprintf(
		// translators: %s is the PayPal order Id.
			__( 'WC order for PayPal order %s not found.', 'woocommerce-paypal-payments' ),
			$request['resource'] !== null && isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
		);

		return $this->log_and_return_response( $message, $response );
	}

	/**
	 * Return and log response with the given message.
	 *
	 * @param string $message The message.
	 * @param array  $response The response.
	 * @return WP_REST_Response
	 */
	private function log_and_return_response( string $message, array $response ): WP_REST_Response {
		$this->logger->warning( $message );
		$response['message'] = $message;

		return new WP_REST_Response( $response );
	}
}
