<?php
/**
 * The endpoint to create an PayPal order.
 *
 * @package Inpsyde\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentMethod;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class CreateOrderEndpoint
 */
class CreateOrderEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-create-order';

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The cart repository.
	 *
	 * @var CartRepository
	 */
	private $repository;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $api_endpoint;

	/**
	 * The payer factory.
	 *
	 * @var PayerFactory
	 */
	private $payer_factory;

	/**
	 * The session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The early order handler.
	 *
	 * @var EarlyOrderHandler
	 */
	private $early_order_handler;

	/**
	 * The current PayPal order in a process.
	 *
	 * @var Order|null
	 */
	private $order;

	/**
	 * CreateOrderEndpoint constructor.
	 *
	 * @param RequestData       $request_data The RequestData object.
	 * @param CartRepository    $repository The CartRepository object.
	 * @param OrderEndpoint     $order_endpoint The OrderEndpoint object.
	 * @param PayerFactory      $payer_factory The PayerFactory object.
	 * @param SessionHandler    $session_handler The SessionHandler object.
	 * @param Settings          $settings The Settings object.
	 * @param EarlyOrderHandler $early_order_handler The EarlyOrderHandler object.
	 */
	public function __construct(
		RequestData $request_data,
		CartRepository $repository,
		OrderEndpoint $order_endpoint,
		PayerFactory $payer_factory,
		SessionHandler $session_handler,
		Settings $settings,
		EarlyOrderHandler $early_order_handler
	) {

		$this->request_data        = $request_data;
		$this->repository          = $repository;
		$this->api_endpoint        = $order_endpoint;
		$this->payer_factory       = $payer_factory;
		$this->session_handler     = $session_handler;
		$this->settings            = $settings;
		$this->early_order_handler = $early_order_handler;
	}

	/**
	 * Returns the nonce.
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
	 * @throws \Inpsyde\PayPalCommerce\WcGateway\Exception\NotFoundException In case a setting was not found.
	 */
	public function handle_request(): bool {
		try {
			$data           = $this->request_data->read_request( $this->nonce() );
			$purchase_units = $this->repository->all();
			$payer          = null;
			if ( isset( $data['payer'] ) && $data['payer'] ) {
				if ( isset( $data['payer']['phone']['phone_number']['national_number'] ) ) {
					// make sure the phone number contains only numbers and is max 14. chars long.
					$number = $data['payer']['phone']['phone_number']['national_number'];
					$number = preg_replace( '/[^0-9]/', '', $number );
					$number = substr( $number, 0, 14 );
					$data['payer']['phone']['phone_number']['national_number'] = $number;
				}
				$payer = $this->payer_factory->from_paypal_response( json_decode( wp_json_encode( $data['payer'] ) ) );
			}
			$bn_code = isset( $data['bn_code'] ) ? (string) $data['bn_code'] : '';
			if ( $bn_code ) {
				$this->session_handler->replace_bn_code( $bn_code );
				$this->api_endpoint->with_bn_code( $bn_code );
			}
			$payee_preferred = $this->settings->has( 'payee_preferred' )
			&& $this->settings->get( 'payee_preferred' ) ?
				PaymentMethod::PAYEE_PREFERRED_IMMEDIATE_PAYMENT_REQUIRED
				: PaymentMethod::PAYEE_PREFERRED_UNRESTRICTED;
			$payment_method  = new PaymentMethod( $payee_preferred );
			$order           = $this->api_endpoint->create(
				$purchase_units,
				$payer,
				null,
				$payment_method
			);
			if ( 'checkout' === $data['context'] ) {
					$this->validateForm( $data['form'], $order );
			}
			wp_send_json_success( $order->to_array() );
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

	/**
	 * Prepare the Request parameter and process the checkout form and validate it.
	 *
	 * @param string $form_values The values of the form.
	 * @param Order  $order The Order.
	 *
	 * @throws \Exception On Error.
	 */
	private function validateForm( string $form_values, Order $order ) {
		$this->order   = $order;
		$parsed_values = wp_parse_args( $form_values );
		$_POST         = $parsed_values;
		$_REQUEST      = $parsed_values;

		add_filter(
			'woocommerce_after_checkout_validation',
			array(
				$this,
				'after_checkout_validation',
			),
			10,
			2
		);
		$checkout = \WC()->checkout();
		$checkout->process_checkout();
	}

	/**
	 * Once the checkout has been validated we execute this method.
	 *
	 * @param array     $data The data.
	 * @param \WP_Error $errors The errors, which occurred.
	 *
	 * @return array
	 */
	public function after_checkout_validation( array $data, \WP_Error $errors ): array {

		$order = $this->order;
		if ( ! $errors->errors ) {

			/**
			 * In case we are onboarded and everything is fine with the \WC_Order
			 * we want this order to be created. We will intercept it and leave it
			 * in the "Pending payment" status though, which than later will change
			 * during the "onApprove"-JS callback or the webhook listener.
			 */
			if ( ! $this->early_order_handler->should_create_early_order() ) {
				wp_send_json_success( $order->to_array() );
			}
			$this->early_order_handler->register_for_order( $order );
			return $data;
		}

		wp_send_json_error(
			array(
				'name'    => '',
				'message' => $errors->get_error_message(),
				'code'    => (int) $errors->get_error_code(),
				'details' => array(),
			)
		);
		return $data;
	}
}
