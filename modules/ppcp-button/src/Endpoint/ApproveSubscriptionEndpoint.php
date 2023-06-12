<?php
/**
 * Endpoint to handle PayPal Subscription created.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Session\SessionHandler;

/**
 * Class ApproveSubscriptionEndpoint
 */
class ApproveSubscriptionEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-approve-subscription';

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * ApproveSubscriptionEndpoint constructor.
	 *
	 * @param RequestData    $request_data The request data helper.
	 * @param OrderEndpoint  $order_endpoint The order endpoint.
	 * @param SessionHandler $session_handler The session handler.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler
	) {
		$this->request_data    = $request_data;
		$this->order_endpoint  = $order_endpoint;
		$this->session_handler = $session_handler;
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 * @throws RuntimeException When order not found or handling failed.
	 */
	public function handle_request(): bool {
		$data = $this->request_data->read_request( $this->nonce() );
		if ( ! isset( $data['order_id'] ) ) {
			throw new RuntimeException(
				__( 'No order id given', 'woocommerce-paypal-payments' )
			);
		}

		$order = $this->order_endpoint->order( $data['order_id'] );
		$this->session_handler->replace_order( $order );

		if ( isset( $data['subscription_id'] ) ) {
			WC()->session->set( 'ppcp_subscription_id', $data['subscription_id'] );
		}

		wp_send_json_success();
		return true;
	}
}
