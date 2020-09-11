<?php
/**
 * Endpoint to verify if an order has been approved. An approved order
 * will be stored in the current session.
 *
 * @package Inpsyde\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Button\Helper\ThreeDSecure;
use Inpsyde\PayPalCommerce\Session\SessionHandler;

/**
 * Class ApproveOrderEndpoint
 */
class ApproveOrderEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-approve-order';

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $api_endpoint;

	/**
	 * The 3d secure helper object.
	 *
	 * @var ThreeDSecure
	 */
	private $threed_secure;

	/**
	 * ApproveOrderEndpoint constructor.
	 *
	 * @param RequestData    $request_data The request data helper.
	 * @param OrderEndpoint  $order_endpoint The order endpoint.
	 * @param SessionHandler $session_handler The session handler.
	 * @param ThreeDSecure   $three_d_secure The 3d secure helper object.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler,
		ThreeDSecure $three_d_secure
	) {

		$this->request_data    = $request_data;
		$this->api_endpoint    = $order_endpoint;
		$this->session_handler = $session_handler;
		$this->threed_secure   = $three_d_secure;
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
	 * @throws RuntimeException When no order was found.
	 */
	public function handle_request(): bool {
		try {
			$data = $this->request_data->read_request( $this->nonce() );
			if ( ! isset( $data['order_id'] ) ) {
				throw new RuntimeException(
					__( 'No order id given', 'paypal-payments-for-woocommerce' )
				);
			}

			$order = $this->api_endpoint->order( $data['order_id'] );
			if ( ! $order ) {
				throw new RuntimeException(
					sprintf(
						// translators: %s is the id of the order.
						__( 'Order %s not found.', 'paypal-payments-for-woocommerce' ),
						$data['order_id']
					)
				);
			}

			if ( $order->payment_source() && $order->payment_source()->card() ) {
				$proceed = $this->threed_secure->proceed_with_order( $order );
				if ( ThreeDSecure::RETRY === $proceed ) {
					throw new RuntimeException(
						__(
							'Something went wrong. Please try again.',
							'paypal-payments-for-woocommerce'
						)
					);
				}
				if ( ThreeDSecure::REJECT === $proceed ) {
					throw new RuntimeException(
						__(
							'Unfortunatly, we can\'t accept your card. Please choose a different payment method.',
							'paypal-payments-for-woocommerce'
						)
					);
				}
				$this->session_handler->replace_order( $order );
				wp_send_json_success( $order );
			}

			if ( ! $order->status()->is( OrderStatus::APPROVED ) ) {
				throw new RuntimeException(
					sprintf(
					// translators: %s is the id of the order.
						__( 'Order %s is not approved yet.', 'paypal-payments-for-woocommerce' ),
						$data['order_id']
					)
				);
			}

			$this->session_handler->replace_order( $order );
			wp_send_json_success( $order );
			return true;
		} catch ( \RuntimeException $error ) {
			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
					'code'    => $error->getCode(),
					'details' => is_a( $error, PayPalApiException::class ) ? $error->details() : array(),
				)
			);
			return false;
		}
	}
}
