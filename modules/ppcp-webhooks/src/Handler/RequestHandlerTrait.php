<?php
/**
 * Trait which helps to handle the request.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WC_Order;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
use WP_REST_Request;
use WP_REST_Response;

trait RequestHandlerTrait {

	/**
	 * Get available custom ids from the given request
	 *
	 * @param WP_REST_Request $request The request.
	 * @return string[]
	 */
	protected function get_custom_ids_from_request( WP_REST_Request $request ): array {
		$resource = $request['resource'];
		if ( ! is_array( $resource ) ) {
			return array();
		}

		$ids = array();
		if ( isset( $resource['custom_id'] ) && ! empty( $resource['custom_id'] ) ) {
			$ids[] = $resource['custom_id'];
		} elseif ( isset( $resource['purchase_units'] ) ) {
			$ids = array_map(
				static function ( array $purchase_unit ): string {
					return $purchase_unit['custom_id'] ?? '';
				},
				(array) $resource['purchase_units']
			);
		}

		return array_values(
			array_filter(
				$ids,
				function ( string $id ): bool {
					return ! empty( $id );
				}
			)
		);
	}

	/**
	 * Get available WC order ids from the given request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return string[]
	 */
	protected function get_wc_order_ids_from_request( WP_REST_Request $request ): array {
		$ids = $this->get_custom_ids_from_request( $request );

		return array_values(
			array_filter(
				$ids,
				function ( string $id ): bool {
					return strpos( $id, CustomIds::CUSTOMER_ID_PREFIX ) === false;
				}
			)
		);
	}

	/**
	 * Get available WC customer ids from the given request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return string[]
	 */
	protected function get_wc_customer_ids_from_request( WP_REST_Request $request ): array {
		$ids = $this->get_custom_ids_from_request( $request );

		$customer_ids = array_values(
			array_filter(
				$ids,
				function ( string $id ): bool {
					return strpos( $id, CustomIds::CUSTOMER_ID_PREFIX ) === 0;
				}
			)
		);
		return array_map(
			function ( string $str ): string {
				return (string) substr( $str, strlen( CustomIds::CUSTOMER_ID_PREFIX ) );
			},
			$customer_ids
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
			'WC order ID was not found in webhook event %s for PayPal order %s.',
			(string) ( $request['id'] ?? '' ),
			// Psalm 4.x does not seem to understand ?? with ArrayAccess correctly.
			$request['resource'] !== null && isset( $request['resource']['id'] ) ? $request['resource']['id'] : ''
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
			'WC order %s not found in webhook event %s for PayPal order %s.',
			implode( ', ', $this->get_custom_ids_from_request( $request ) ),
			(string) ( $request['id'] ?? '' ),
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
