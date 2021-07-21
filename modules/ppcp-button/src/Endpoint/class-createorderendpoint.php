<?php
/**
 * The endpoint to create an PayPal order.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

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
	 */
	public function __construct(
		RequestData $request_data,
		CartRepository $cart_repository,
		PurchaseUnitFactory $purchase_unit_factory,
		OrderEndpoint $order_endpoint,
		PayerFactory $payer_factory,
		SessionHandler $session_handler,
		Settings $settings,
		EarlyOrderHandler $early_order_handler
	) {

		$this->request_data          = $request_data;
		$this->cart_repository       = $cart_repository;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->api_endpoint          = $order_endpoint;
		$this->payer_factory         = $payer_factory;
		$this->session_handler       = $session_handler;
		$this->settings              = $settings;
		$this->early_order_handler   = $early_order_handler;
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
				if ( isset( $data['createaccount'] ) && '1' === $data['createaccount'] ) {
					$this->process_checkout_form_when_creating_account( $data['form'], $wc_order );
				}

				$this->process_checkout_form( $data['form'] );
			}
			if ( 'pay-now' === $data['context'] && get_option( 'woocommerce_terms_page_id', '' ) !== '' ) {
				$this->validate_paynow_form( $data['form'] );
			}

			// if we are here so the context is not 'checkout' as it exits before. Therefore, a PayPal order is not created yet.
			// It would be a good idea to refactor the checkout process in the future.
			$order = $this->create_paypal_order( $wc_order );
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
		} catch ( \Exception $exception ) {
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
	 */
	public function after_checkout_validation( array $data, \WP_Error $errors ): array {
		if ( ! $errors->errors ) {

			$order = $this->create_paypal_order();

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
		$needs_shipping          = WC()->cart && WC()->cart->needs_shipping();
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
	 * Prepare the Request parameter and process the checkout form and validate it.
	 *
	 * @param string $form_values The values of the form.
	 *
	 * @throws \Exception On Error.
	 */
	private function process_checkout_form( string $form_values ) {
		$form_values = explode( '&', $form_values );

		$parsed_values = array();
		foreach ( $form_values as $field ) {
			$field = explode( '=', $field );

			if ( count( $field ) !== 2 ) {
				continue;
			}
			$parsed_values[ $field[0] ] = $field[1];
		}
		$_POST    = $parsed_values;
		$_REQUEST = $parsed_values;

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
	 * Checks whether the terms input field is checked.
	 *
	 * @param string $form_values The form values.
	 * @throws \RuntimeException When field is not checked.
	 */
	private function validate_paynow_form( string $form_values ) {
		$parsed_values = wp_parse_args( $form_values );
		if ( ! isset( $parsed_values['terms'] ) ) {
			throw new \RuntimeException(
				__( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-paypal-payments' )
			);
		}
	}

	/**
	 * Processes checkout and creates the PayPal order after success form validation.
	 *
	 * @param string         $form_values The values of the form.
	 * @param \WC_Order|null $wc_order WC order to get data from.
	 * @throws \Exception On Error.
	 */
	private function process_checkout_form_when_creating_account( string $form_values, \WC_Order $wc_order = null ) {
		$form_values   = explode( '&', $form_values );
		$parsed_values = array();
		foreach ( $form_values as $field ) {
			$field = explode( '=', $field );

			if ( count( $field ) !== 2 ) {
				continue;
			}
			$parsed_values[ $field[0] ] = $field[1];
		}
		$_POST    = $parsed_values;
		$_REQUEST = $parsed_values;

		add_action(
			'woocommerce_after_checkout_validation',
			function ( array $data, \WP_Error $errors ) use ( $wc_order ) {
				if ( ! $errors->errors ) {
					$order = $this->create_paypal_order( $wc_order );
					wp_send_json_success( $order->to_array() );
					return true;
				}
			},
			10,
			2
		);

		$checkout = \WC()->checkout();
		$checkout->process_checkout();
	}
}
