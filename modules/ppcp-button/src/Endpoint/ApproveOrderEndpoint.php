<?php
/**
 * Endpoint to verify if an order has been approved. An approved order
 * will be stored in the current session.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

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
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The DCC applies object.
	 *
	 * @var DccApplies
	 */
	private $dcc_applies;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * ApproveOrderEndpoint constructor.
	 *
	 * @param RequestData     $request_data The request data helper.
	 * @param OrderEndpoint   $order_endpoint The order endpoint.
	 * @param SessionHandler  $session_handler The session handler.
	 * @param ThreeDSecure    $three_d_secure The 3d secure helper object.
	 * @param Settings        $settings The settings.
	 * @param DccApplies      $dcc_applies The DCC applies object.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler,
		ThreeDSecure $three_d_secure,
		Settings $settings,
		DccApplies $dcc_applies,
		LoggerInterface $logger
	) {

		$this->request_data    = $request_data;
		$this->api_endpoint    = $order_endpoint;
		$this->session_handler = $session_handler;
		$this->threed_secure   = $three_d_secure;
		$this->settings        = $settings;
		$this->dcc_applies     = $dcc_applies;
		$this->logger          = $logger;
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
					__( 'No order id given', 'woocommerce-paypal-payments' )
				);
			}

			$order = $this->api_endpoint->order( $data['order_id'] );
			if ( ! $order ) {
				throw new RuntimeException(
					sprintf(
						// translators: %s is the id of the order.
						__( 'Order %s not found.', 'woocommerce-paypal-payments' ),
						$data['order_id']
					)
				);
			}

			if ( $order->payment_source() && $order->payment_source()->card() ) {
				if ( $this->settings->has( 'disable_cards' ) ) {
					$disabled_cards = (array) $this->settings->get( 'disable_cards' );
					$card           = strtolower( $order->payment_source()->card()->brand() );
					if ( 'master_card' === $card ) {
						$card = 'mastercard';
					}

					if ( ! $this->dcc_applies->can_process_card( $card ) || in_array( $card, $disabled_cards, true ) ) {
						throw new RuntimeException(
							__(
								'Unfortunately, we do not accept this card.',
								'woocommerce-paypal-payments'
							),
							100
						);
					}
				}
				$proceed = $this->threed_secure->proceed_with_order( $order );
				if ( ThreeDSecure::RETRY === $proceed ) {
					throw new RuntimeException(
						__(
							'Something went wrong. Please try again.',
							'woocommerce-paypal-payments'
						)
					);
				}
				if ( ThreeDSecure::REJECT === $proceed ) {
					throw new RuntimeException(
						__(
							'Unfortunately, we can\'t accept your card. Please choose a different payment method.',
							'woocommerce-paypal-payments'
						)
					);
				}
				$this->session_handler->replace_order( $order );
				wp_send_json_success( $order );
			}

			if ( ! $order->status()->is( OrderStatus::APPROVED ) ) {
				$message = sprintf(
				// translators: %s is the id of the order.
					__( 'Order %s is not approved yet.', 'woocommerce-paypal-payments' ),
					$data['order_id']
				);

				$this->logger->log( 'error', $message );
				throw new RuntimeException( $message );
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
