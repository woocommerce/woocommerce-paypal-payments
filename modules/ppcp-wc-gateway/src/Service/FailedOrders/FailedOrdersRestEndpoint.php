<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Service\FailedOrders;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Controller;

/**
 * REST controller for retrieving failed order data.
 */
class FailedOrdersRestEndpoint extends WP_REST_Controller {
	/**
	 * Endpoint namespace.
	 */
	protected const NAMESPACE = 'wc/v3/wc_paypal';

	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'failed_orders';

	/**
	 * Failed order tracker service.
	 *
	 * @var FailedOrderTracker
	 */
	protected FailedOrderTracker $failed_order_tracker;

	/**
	 * Constructor.
	 *
	 * @param FailedOrderTracker $failed_order_tracker Failed order tracker service.
	 */
	public function __construct( FailedOrderTracker $failed_order_tracker ) {
		$this->failed_order_tracker = $failed_order_tracker;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes(): void {
		/**
		 * GET /wp-json/wc/v3/wc_paypal/failed_orders
		 * Query parameters:
		 * - limit: Number of orders to retrieve (default: 10)
		 * - minutes: Time period in minutes for count (default: 60)
		 */
		register_rest_route(
			static::NAMESPACE,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_failed_orders' ),
				'permission_callback' => current_user_can( 'manage_woocommerce' ),
				'args'                => array(
					'limit'   => array(
						'default'           => 10,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'minutes' => array(
						'default'           => 60,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Returns recent failed orders.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The failed orders data or an error response.
	 */
	public function get_failed_orders( WP_REST_Request $request ): WP_REST_Response {
		$limit = $request->get_param( 'limit' );

		try {
			$failed_orders = $this->failed_order_tracker->get_recent_failed_orders( $limit );

			return rest_ensure_response( $failed_orders );
		} catch ( \Exception $e ) {
			return rest_ensure_response( new \WP_Error( 'failed_orders_error', $e->getMessage() ) );
		}
	}

	/**
	 * Returns the count of failed orders for a time period.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The failed orders count or an error response.
	 */
	public function get_failed_orders_count( WP_REST_Request $request ): WP_REST_Response {
		$minutes = $request->get_param( 'minutes' );

		try {
			$count = $this->failed_order_tracker->get_failed_orders_count( $minutes );

			return rest_ensure_response(
				array(
					'count'               => $count,
					'time_period_minutes' => $minutes,
				)
			);
		} catch ( \Exception $e ) {
			return rest_ensure_response( new \WP_Error( 'failed_orders_count_error', $e->getMessage() ) );
		}
	}
}
