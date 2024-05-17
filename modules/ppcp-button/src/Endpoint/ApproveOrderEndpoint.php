<?php
/**
 * Endpoint to verify if an order has been approved. An approved order
 * will be stored in the current session.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderHelper;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\ContextTrait;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class ApproveOrderEndpoint
 */
class ApproveOrderEndpoint implements EndpointInterface {

	use ContextTrait;

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
	 * The order helper.
	 *
	 * @var OrderHelper
	 */
	protected $order_helper;

	/**
	 * Whether the final review is enabled.
	 *
	 * @var bool
	 */
	protected $final_review_enabled;

	/**
	 * The WC gateway.
	 *
	 * @var PayPalGateway
	 */
	protected $gateway;

	/**
	 * The WooCommerce order creator.
	 *
	 * @var WooCommerceOrderCreator
	 */
	protected $wc_order_creator;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * ApproveOrderEndpoint constructor.
	 *
	 * @param RequestData             $request_data The request data helper.
	 * @param OrderEndpoint           $order_endpoint The order endpoint.
	 * @param SessionHandler          $session_handler The session handler.
	 * @param ThreeDSecure            $three_d_secure The 3d secure helper object.
	 * @param Settings                $settings The settings.
	 * @param DccApplies              $dcc_applies The DCC applies object.
	 * @param OrderHelper             $order_helper The order helper.
	 * @param bool                    $final_review_enabled Whether the final review is enabled.
	 * @param PayPalGateway           $gateway The WC gateway.
	 * @param WooCommerceOrderCreator $wc_order_creator The WooCommerce order creator.
	 * @param LoggerInterface         $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler,
		ThreeDSecure $three_d_secure,
		Settings $settings,
		DccApplies $dcc_applies,
		OrderHelper $order_helper,
		bool $final_review_enabled,
		PayPalGateway $gateway,
		WooCommerceOrderCreator $wc_order_creator,
		LoggerInterface $logger
	) {

		$this->request_data         = $request_data;
		$this->api_endpoint         = $order_endpoint;
		$this->session_handler      = $session_handler;
		$this->threed_secure        = $three_d_secure;
		$this->settings             = $settings;
		$this->dcc_applies          = $dcc_applies;
		$this->order_helper         = $order_helper;
		$this->final_review_enabled = $final_review_enabled;
		$this->gateway              = $gateway;
		$this->wc_order_creator     = $wc_order_creator;
		$this->logger               = $logger;
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
		try {
			$data = $this->request_data->read_request( $this->nonce() );
			if ( ! isset( $data['order_id'] ) ) {
				throw new RuntimeException(
					__( 'No order id given', 'woocommerce-paypal-payments' )
				);
			}

			$order = $this->api_endpoint->order( $data['order_id'] );

			$payment_source = $order->payment_source();
			if ( $payment_source && $payment_source->name() === 'card' ) {
				if ( $this->settings->has( 'disable_cards' ) ) {
					$disabled_cards = (array) $this->settings->get( 'disable_cards' );
					$card           = strtolower( $payment_source->properties()->brand ?? '' );
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

				wp_send_json_success();
			}

			if ( $this->order_helper->contains_physical_goods( $order ) && ! $order->status()->is( OrderStatus::APPROVED ) && ! $order->status()->is( OrderStatus::CREATED ) ) {
				$message = sprintf(
				// translators: %s is the id of the order.
					__( 'Order %s is not ready for processing yet.', 'woocommerce-paypal-payments' ),
					$data['order_id']
				);

				$this->logger->log( 'error', $message );
				throw new RuntimeException( $message );
			}

			$funding_source = $data['funding_source'] ?? null;
			$this->session_handler->replace_funding_source( $funding_source );

			$this->session_handler->replace_order( $order );

			$should_create_wc_order = $data['should_create_wc_order'] ?? false;
			if ( ! $this->final_review_enabled && ! $this->is_checkout() && $should_create_wc_order ) {
				$wc_order = $this->wc_order_creator->create_from_paypal_order( $order, WC()->cart );
				$this->gateway->process_payment( $wc_order->get_id() );
				$order_received_url = $wc_order->get_checkout_order_received_url();

				wp_send_json_success( array( 'order_received_url' => $order_received_url ) );
			}
			wp_send_json_success();
			return true;
		} catch ( Exception $error ) {
			$this->logger->error( 'Order approve failed: ' . $error->getMessage() );

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
