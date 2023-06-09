<?php
/**
 * Trait which helps to handle the request.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

trait RequestHandlerTrait {

	/**
	 * Get available custom ids from the given request
	 *
	 * @param WP_REST_Request $request The request.
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
		$order_ids = $custom_ids;
		$args      = array(
			'post__in' => $order_ids,
			'limit'    => -1,
		);

		$orders = wc_get_orders( $args );
		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Logs and returns response for no custom ids found in request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	protected function no_custom_ids_response( WP_REST_Request $request ): WP_REST_Response {
		$message = sprintf(
			'No order for webhook event %s was found.',
			$request['id'] !== null && isset( $request['id'] ) ? $request['id'] : ''
		);

		return $this->failure_response( $message );
	}

	/**
	 * Logs and returns response for no WC orders found via custom ids.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	protected function no_wc_orders_response( WP_REST_Request $request ): WP_REST_Response {
		$message = sprintf(
			'WC order for PayPal order %s not found.',
			$request['resource'] !== null && isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
		);

		return $this->failure_response( $message );
	}

	/**
	 * Returns success response.
	 *
	 * @return WP_REST_Response
	 */
	protected function success_response(): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Logs and returns failure response with the given message.
	 *
	 * @param string $message The message.
	 * @return WP_REST_Response
	 */
	private function failure_response( string $message = '' ): WP_REST_Response {
		$response = array(
			'success' => false,
		);
		if ( $message ) {
			$this->logger->warning( $message );
			$response['message'] = $message;
		}

		return new WP_REST_Response( $response );
	}
}
