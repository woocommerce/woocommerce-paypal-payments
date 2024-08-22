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
use WooCommerce\PayPalCommerce\Button\Helper\ContextTrait;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class ApproveSubscriptionEndpoint
 */
class ApproveSubscriptionEndpoint implements EndpointInterface {

	use ContextTrait;

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
	 * Whether the final review is enabled.
	 *
	 * @var bool
	 */
	protected $final_review_enabled;

	/**
	 * The WooCommerce order creator.
	 *
	 * @var WooCommerceOrderCreator
	 */
	protected $wc_order_creator;

	/**
	 * The WC gateway.
	 *
	 * @var PayPalGateway
	 */
	protected $gateway;

	/**
	 * ApproveSubscriptionEndpoint constructor.
	 *
	 * @param RequestData             $request_data The request data helper.
	 * @param OrderEndpoint           $order_endpoint The order endpoint.
	 * @param SessionHandler          $session_handler The session handler.
	 * @param bool                    $final_review_enabled Whether the final review is enabled.
	 * @param WooCommerceOrderCreator $wc_order_creator The WooCommerce order creator.
	 * @param PayPalGateway           $gateway The WC gateway.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler,
		bool $final_review_enabled,
		WooCommerceOrderCreator $wc_order_creator,
		PayPalGateway $gateway
	) {
		$this->request_data         = $request_data;
		$this->order_endpoint       = $order_endpoint;
		$this->session_handler      = $session_handler;
		$this->final_review_enabled = $final_review_enabled;
		$this->wc_order_creator     = $wc_order_creator;
		$this->gateway              = $gateway;
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

		$should_create_wc_order = $data['should_create_wc_order'] ?? false;
		if ( ! $this->final_review_enabled && ! $this->is_checkout() && $should_create_wc_order ) {
			$wc_order = $this->wc_order_creator->create_from_paypal_order( $order, WC()->cart );
			$this->gateway->process_payment( $wc_order->get_id() );
			$order_received_url = $wc_order->get_checkout_order_received_url();

			wp_send_json_success( array( 'order_received_url' => $order_received_url ) );
		}

		wp_send_json_success();
		return true;
	}
}
