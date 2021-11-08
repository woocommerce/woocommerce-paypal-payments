<?php
/**
 * The endpoint to create an PayPal order.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentMethod;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CartRepository;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

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
	private $cart_repository;

	/**
	 * The PurchaseUnit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

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
	 * Data from the request.
	 *
	 * @var array
	 */
	private $parsed_request_data;

	/**
	 * The array of purchase units for order.
	 *
	 * @var PurchaseUnit[]
	 */
	private $purchase_units;

	/**
	 * Whether a new user must be registered during checkout.
	 *
	 * @var bool
	 */
	private $registration_needed;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * CreateOrderEndpoint constructor.
	 *
	 * @param RequestData         $request_data The RequestData object.
	 * @param CartRepository      $cart_repository The CartRepository object.
	 * @param PurchaseUnitFactory $purchase_unit_factory The Purchaseunit factory.
	 * @param OrderEndpoint       $order_endpoint The OrderEndpoint object.
	 * @param PayerFactory        $payer_factory The PayerFactory object.
	 * @param SessionHandler      $session_handler The SessionHandler object.
	 * @param Settings            $settings The Settings object.
	 * @param EarlyOrderHandler   $early_order_handler The EarlyOrderHandler object.
	 * @param bool                $registration_needed  Whether a new user must be registered during checkout.
	 * @param LoggerInterface     $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		CartRepository $cart_repository,
		PurchaseUnitFactory $purchase_unit_factory,
		OrderEndpoint $order_endpoint,
		PayerFactory $payer_factory,
		SessionHandler $session_handler,
		Settings $settings,
		EarlyOrderHandler $early_order_handler,
		bool $registration_needed,
		LoggerInterface $logger
	) {

		$this->request_data          = $request_data;
		$this->cart_repository       = $cart_repository;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->api_endpoint          = $order_endpoint;
		$this->payer_factory         = $payer_factory;
		$this->session_handler       = $session_handler;
		$this->settings              = $settings;
		$this->early_order_handler   = $early_order_handler;
		$this->registration_needed   = $registration_needed;
		$this->logger                = $logger;
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
	 * @throws Exception On Error.
	 */
	public function handle_request(): bool {
		try {
			$data                      = $this->request_data->read_request( $this->nonce() );
			$this->parsed_request_data = $data;
			$wc_order                  = null;
			if ( 'pay-now' === $data['context'] ) {
				$wc_order = wc_get_order( (int) $data['order_id'] );
				if ( ! is_a( $wc_order, \WC_Order::class ) ) {
					wp_send_json_error(
						array(
							'name'    => 'order-not-found',
							'message' => __( 'Order not found', 'woocommerce-paypal-payments' ),
							'code'    => 0,
							'details' => array(),
						)
					);
				}
				$this->purchase_units = array( $this->purchase_unit_factory->from_wc_order( $wc_order ) );
			} else {
				$this->purchase_units = $this->cart_repository->all();
			}

			$this->set_bn_code( $data );

			if ( 'checkout' === $data['context'] ) {
				try {
					$order = $this->create_paypal_order( $wc_order );
				} catch ( Exception $exception ) {
					$this->logger->error( 'Order creation failed: ' . $exception->getMessage() );
					throw $exception;
				}

				if (
					! $this->early_order_handler->should_create_early_order()
					|| $this->registration_needed
					|| isset( $data['createaccount'] ) && '1' === $data['createaccount'] ) {
					wp_send_json_success( $order->to_array() );
				}

				$this->early_order_handler->register_for_order( $order );
			}

			if ( 'pay-now' === $data['context'] && get_option( 'woocommerce_terms_page_id', '' ) !== '' ) {
				$this->validate_paynow_form( $data['form'] );
			}

			$order = $this->create_paypal_order( $wc_order );
			wp_send_json_success( $order->to_array() );
			return true;

		} catch ( \RuntimeException $error ) {
			$this->logger->error( 'Order creation failed: ' . $error->getMessage() );

			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
					'code'    => $error->getCode(),
					'details' => is_a( $error, PayPalApiException::class ) ? $error->details() : array(),
				)
			);
		} catch ( Exception $exception ) {
			$this->logger->error( 'Order creation failed: ' . $exception->getMessage() );

			wc_add_notice( $exception->getMessage(), 'error' );
		}

		return false;
	}

	/**
	 * Once the checkout has been validated we execute this method.
	 *
	 * @param array     $data The data.
	 * @param \WP_Error $errors The errors, which occurred.
	 *
	 * @return array
	 * @throws Exception On Error.
	 */
	public function after_checkout_validation( array $data, \WP_Error $errors ): array {
		if ( ! $errors->errors ) {
			try {
				$order = $this->create_paypal_order();
			} catch ( Exception $exception ) {
				$this->logger->error( 'Order creation failed: ' . $exception->getMessage() );
				throw $exception;
			}

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

		$this->logger->error( 'Checkout validation failed: ' . $errors->get_error_message() );

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

	/**
	 * Creates the order in the PayPal, uses data from WC order if provided.
	 *
	 * @param \WC_Order|null $wc_order WC order to get data from.
	 *
	 * @return Order Created PayPal order.
	 *
	 * @throws RuntimeException If create order request fails.
	 */
	private function create_paypal_order( \WC_Order $wc_order = null ): Order {
		$needs_shipping          = WC()->cart instanceof \WC_Cart && WC()->cart->needs_shipping();
		$shipping_address_is_fix = $needs_shipping && 'checkout' === $this->parsed_request_data['context'];

		return $this->api_endpoint->create(
			$this->purchase_units,
			$this->payer( $this->parsed_request_data, $wc_order ),
			null,
			$this->payment_method(),
			'',
			$shipping_address_is_fix
		);
	}

	/**
	 * Returns the Payer entity based on the request data.
	 *
	 * @param array          $data The request data.
	 * @param \WC_Order|null $wc_order The order.
	 *
	 * @return Payer|null
	 */
	private function payer( array $data, \WC_Order $wc_order = null ) {
		if ( 'pay-now' === $data['context'] ) {
			$payer = $this->payer_factory->from_wc_order( $wc_order );
			return $payer;
		}

		$payer = null;
		if ( isset( $data['payer'] ) && $data['payer'] ) {
			if ( isset( $data['payer']['phone']['phone_number']['national_number'] ) ) {
				// make sure the phone number contains only numbers and is max 14. chars long.
				$number = $data['payer']['phone']['phone_number']['national_number'];
				$number = preg_replace( '/[^0-9]/', '', $number );
				$number = substr( $number, 0, 14 );
				$data['payer']['phone']['phone_number']['national_number'] = $number;
				if ( empty( $data['payer']['phone']['phone_number']['national_number'] ) ) {
					unset( $data['payer']['phone'] );
				}
			}

			$payer = $this->payer_factory->from_paypal_response( json_decode( wp_json_encode( $data['payer'] ) ) );
		}
		return $payer;
	}

	/**
	 * Sets the BN Code for the following request.
	 *
	 * @param array $data The request data.
	 */
	private function set_bn_code( array $data ) {
		$bn_code = isset( $data['bn_code'] ) ? (string) $data['bn_code'] : '';
		if ( ! $bn_code ) {
			return;
		}

		$this->session_handler->replace_bn_code( $bn_code );
		$this->api_endpoint->with_bn_code( $bn_code );
	}

	/**
	 * Returns the PaymentMethod object for the order.
	 *
	 * @return PaymentMethod
	 */
	private function payment_method() : PaymentMethod {
		try {
			$payee_preferred = $this->settings->has( 'payee_preferred' ) && $this->settings->get( 'payee_preferred' ) ?
				PaymentMethod::PAYEE_PREFERRED_IMMEDIATE_PAYMENT_REQUIRED
				: PaymentMethod::PAYEE_PREFERRED_UNRESTRICTED;
		} catch ( NotFoundException $exception ) {
			$payee_preferred = PaymentMethod::PAYEE_PREFERRED_UNRESTRICTED;
		}

		$payment_method = new PaymentMethod( $payee_preferred );
		return $payment_method;
	}

	/**
	 * Checks whether the terms input field is checked.
	 *
	 * @param string $form_values The form values.
	 * @throws \RuntimeException When field is not checked.
	 */
	private function validate_paynow_form( string $form_values ) {
		$parsed_values = wp_parse_args( $form_values );
		if ( isset( $parsed_values['terms-field'] ) && ! isset( $parsed_values['terms'] ) ) {
			throw new \RuntimeException(
				__( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-paypal-payments' )
			);
		}
	}
}
