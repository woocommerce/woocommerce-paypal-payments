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
use stdClass;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Validation\CheckoutFormValidator;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcGateway\CardBillingMode;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class CreateOrderEndpoint
 */
class CreateOrderEndpoint implements EndpointInterface {

	use FreeTrialHandlerTrait;

	const ENDPOINT = 'ppc-create-order';

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The PurchaseUnit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The shipping_preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	private $shipping_preference_factory;

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
	 * The purchase unit for order.
	 *
	 * @var PurchaseUnit|null
	 */
	private $purchase_unit;

	/**
	 * Whether a new user must be registered during checkout.
	 *
	 * @var bool
	 */
	private $registration_needed;

	/**
	 * The value of card_billing_data_mode from the settings.
	 *
	 * @var string
	 */
	protected $card_billing_data_mode;

	/**
	 * Whether to execute WC validation of the checkout form.
	 *
	 * @var bool
	 */
	protected $early_validation_enabled;

	/**
	 * The contexts that should have the Pay Now button.
	 *
	 * @var string[]
	 */
	private $pay_now_contexts;

	/**
	 * If true, the shipping methods are sent to PayPal allowing the customer to select it inside the popup.
	 *
	 * @var bool
	 */
	private $handle_shipping_in_paypal;

	/**
	 * The sources that do not cause issues about redirecting (on mobile, ...) and sometimes not returning back.
	 *
	 * @var string[]
	 */
	private $funding_sources_without_redirect;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The form data, or empty if not available.
	 *
	 * @var array
	 */
	private $form = array();

	/**
	 * CreateOrderEndpoint constructor.
	 *
	 * @param RequestData               $request_data The RequestData object.
	 * @param PurchaseUnitFactory       $purchase_unit_factory The PurchaseUnit factory.
	 * @param ShippingPreferenceFactory $shipping_preference_factory The shipping_preference factory.
	 * @param OrderEndpoint             $order_endpoint The OrderEndpoint object.
	 * @param PayerFactory              $payer_factory The PayerFactory object.
	 * @param SessionHandler            $session_handler The SessionHandler object.
	 * @param Settings                  $settings The Settings object.
	 * @param EarlyOrderHandler         $early_order_handler The EarlyOrderHandler object.
	 * @param bool                      $registration_needed  Whether a new user must be registered during checkout.
	 * @param string                    $card_billing_data_mode The value of card_billing_data_mode from the settings.
	 * @param bool                      $early_validation_enabled Whether to execute WC validation of the checkout form.
	 * @param string[]                  $pay_now_contexts The contexts that should have the Pay Now button.
	 * @param bool                      $handle_shipping_in_paypal If true, the shipping methods are sent to PayPal allowing the customer to select it inside the popup.
	 * @param string[]                  $funding_sources_without_redirect The sources that do not cause issues about redirecting (on mobile, ...) and sometimes not returning back.
	 * @param LoggerInterface           $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		OrderEndpoint $order_endpoint,
		PayerFactory $payer_factory,
		SessionHandler $session_handler,
		Settings $settings,
		EarlyOrderHandler $early_order_handler,
		bool $registration_needed,
		string $card_billing_data_mode,
		bool $early_validation_enabled,
		array $pay_now_contexts,
		bool $handle_shipping_in_paypal,
		array $funding_sources_without_redirect,
		LoggerInterface $logger
	) {

		$this->request_data                     = $request_data;
		$this->purchase_unit_factory            = $purchase_unit_factory;
		$this->shipping_preference_factory      = $shipping_preference_factory;
		$this->api_endpoint                     = $order_endpoint;
		$this->payer_factory                    = $payer_factory;
		$this->session_handler                  = $session_handler;
		$this->settings                         = $settings;
		$this->early_order_handler              = $early_order_handler;
		$this->registration_needed              = $registration_needed;
		$this->card_billing_data_mode           = $card_billing_data_mode;
		$this->early_validation_enabled         = $early_validation_enabled;
		$this->pay_now_contexts                 = $pay_now_contexts;
		$this->handle_shipping_in_paypal        = $handle_shipping_in_paypal;
		$this->funding_sources_without_redirect = $funding_sources_without_redirect;
		$this->logger                           = $logger;
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
			$payment_method            = $data['payment_method'] ?? '';
			$funding_source            = $data['funding_source'] ?? '';
			$payment_source            = $data['payment_source'] ?? '';
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
				$this->purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
			} else {
				$this->purchase_unit = $this->purchase_unit_factory->from_wc_cart( null, $this->should_handle_shipping_in_paypal( $payment_source ) );

				// Do not allow completion by webhooks when started via non-checkout buttons,
				// it is needed only for some APMs in checkout.
				if ( in_array( $data['context'], array( 'product', 'cart', 'cart-block' ), true ) ) {
					$this->purchase_unit->set_custom_id( '' );
				}

				// The cart does not have any info about payment method, so we must handle free trial here.
				if ( (
					in_array( $payment_method, array( CreditCardGateway::ID, CardButtonGateway::ID ), true )
						|| ( PayPalGateway::ID === $payment_method && 'card' === $funding_source )
					)
					&& $this->is_free_trial_cart()
				) {
					$this->purchase_unit->set_amount(
						new Amount(
							new Money( 1.0, $this->purchase_unit->amount()->currency_code() ),
							$this->purchase_unit->amount()->breakdown()
						)
					);
				}
			}

			$this->set_bn_code( $data );

			if ( isset( $data['form'] ) ) {
				$this->form = $data['form'];
			}

			if ( $this->early_validation_enabled
				&& $this->form
				&& 'checkout' === $data['context']
				&& in_array( $payment_method, array( PayPalGateway::ID, CardButtonGateway::ID, CreditCardGateway::ID ), true )
			) {
				$this->validate_form( $this->form );
			}

			if ( 'pay-now' === $data['context'] && $this->form && get_option( 'woocommerce_terms_page_id', '' ) !== '' ) {
				$this->validate_paynow_form( $this->form );
			}

			try {
				$order = $this->create_paypal_order( $wc_order, $payment_method, $data );
			} catch ( Exception $exception ) {
				$this->logger->error( 'Order creation failed: ' . $exception->getMessage() );
				throw $exception;
			}

			if ( 'checkout' === $data['context'] ) {
				if ( $payment_method === PayPalGateway::ID && ! in_array( $funding_source, $this->funding_sources_without_redirect, true ) ) {
					$this->session_handler->replace_order( $order );
					$this->session_handler->replace_funding_source( $funding_source );
				}

				if (
					! $this->early_order_handler->should_create_early_order()
					|| $this->registration_needed
					|| isset( $data['createaccount'] ) && '1' === $data['createaccount'] ) {
					wp_send_json_success( $this->make_response( $order ) );
				}

				$this->early_order_handler->register_for_order( $order );
			}

			if ( 'pay-now' === $data['context'] && is_a( $wc_order, \WC_Order::class ) ) {
				$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, $order->id() );
				$wc_order->update_meta_data( PayPalGateway::INTENT_META_KEY, $order->intent() );

				$payment_source      = $order->payment_source();
				$payment_source_name = $payment_source ? $payment_source->name() : null;
				$payer               = $order->payer();
				if (
					$payer
					&& $payment_source_name
					&& in_array( $payment_source_name, PayPalGateway::PAYMENT_SOURCES_WITH_PAYER_EMAIL, true )
				) {
					$payer_email = $payer->email_address();
					if ( $payer_email ) {
						$wc_order->update_meta_data( PayPalGateway::ORDER_PAYER_EMAIL_META_KEY, $payer_email );
					}
				}

				$wc_order->save_meta_data();

				do_action( 'woocommerce_paypal_payments_woocommerce_order_created', $wc_order, $order );
			}

			wp_send_json_success( $this->make_response( $order ) );
			return true;

		} catch ( ValidationException $error ) {
			$response = array(
				'message' => $error->getMessage(),
				'errors'  => $error->errors(),
				'refresh' => isset( WC()->session->refresh_totals ),
			);

			unset( WC()->session->refresh_totals );

			wp_send_json_error( $response );
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
				wp_send_json_success( $this->make_response( $order ) );
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
	 * @param string         $payment_method WC payment method.
	 * @param array          $data Request data.
	 *
	 * @return Order Created PayPal order.
	 *
	 * @throws RuntimeException If create order request fails.
	 * @throws PayPalApiException If create order request fails.
	 *
	 * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
	 */
	private function create_paypal_order( \WC_Order $wc_order = null, string $payment_method = '', array $data = array() ): Order {
		assert( $this->purchase_unit instanceof PurchaseUnit );

		$funding_source = $this->parsed_request_data['funding_source'] ?? '';
		$payer          = $this->payer( $this->parsed_request_data, $wc_order );

		$shipping_preference = $this->shipping_preference_factory->from_state(
			$this->purchase_unit,
			$this->parsed_request_data['context'],
			WC()->cart,
			$funding_source
		);

		$action = in_array( $this->parsed_request_data['context'], $this->pay_now_contexts, true ) ?
			ApplicationContext::USER_ACTION_PAY_NOW : ApplicationContext::USER_ACTION_CONTINUE;

		if ( 'card' === $funding_source ) {
			if ( CardBillingMode::MINIMAL_INPUT === $this->card_billing_data_mode ) {
				if ( ApplicationContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS === $shipping_preference ) {
					if ( $payer ) {
						$payer->set_address( null );
					}
				}
				if ( ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING === $shipping_preference ) {
					if ( $payer ) {
						$payer->set_name( null );
					}
				}
			}

			if ( CardBillingMode::NO_WC === $this->card_billing_data_mode ) {
				$payer = null;
			}
		}

		try {
			return $this->api_endpoint->create(
				array( $this->purchase_unit ),
				$shipping_preference,
				$payer,
				null,
				'',
				$action,
				$payment_method,
				$data
			);
		} catch ( PayPalApiException $exception ) {
			// Looks like currently there is no proper way to validate the shipping address for PayPal,
			// so we cannot make some invalid addresses null in PurchaseUnitFactory,
			// which causes failure e.g. for guests using the button on products pages when the country does not have postal codes.
			if ( 422 === $exception->status_code()
				&& array_filter(
					$exception->details(),
					function ( stdClass $detail ): bool {
						return isset( $detail->field ) && str_contains( (string) $detail->field, 'shipping/address' );
					}
				) ) {
				$this->logger->info( 'Invalid shipping address for order creation, retrying without it.' );

				$this->purchase_unit->set_shipping( null );

				return $this->api_endpoint->create(
					array( $this->purchase_unit ),
					$shipping_preference,
					$payer,
					null
				);
			}

			throw $exception;
		}
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

		if ( ! $payer && $this->form ) {
			if ( isset( $this->form['billing_email'] ) && '' !== $this->form['billing_email'] ) {
				return $this->payer_factory->from_checkout_form( $this->form );
			}
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
	 * Checks whether the form fields are valid.
	 *
	 * @param array $form_fields The form fields.
	 * @throws ValidationException When fields are not valid.
	 */
	private function validate_form( array $form_fields ): void {
		try {
			$v = new CheckoutFormValidator();
			$v->validate( $form_fields );
		} catch ( ValidationException $exception ) {
			throw $exception;
		} catch ( Throwable $exception ) {
			$this->logger->error( "Form validation execution failed. {$exception->getMessage()} {$exception->getFile()}:{$exception->getLine()}" );
		}
	}

	/**
	 * Checks whether the terms input field is checked.
	 *
	 * @param array $form_fields The form fields.
	 * @throws ValidationException When field is not checked.
	 */
	private function validate_paynow_form( array $form_fields ): void {
		if ( isset( $form_fields['terms-field'] ) && ! isset( $form_fields['terms'] ) ) {
			throw new ValidationException(
				array( __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-paypal-payments' ) )
			);
		}
	}

	/**
	 * Returns the response data for success response.
	 *
	 * @param Order $order The PayPal order.
	 * @return array
	 */
	private function make_response( Order $order ): array {
		return array(
			'id'        => $order->id(),
			'custom_id' => $order->purchase_units()[0]->custom_id(),
		);
	}

	/**
	 * Checks if the shipping should be handled in PayPal popup.
	 *
	 * @param string $payment_source The payment source.
	 * @return bool true if the shipping should be handled in PayPal popup, otherwise false.
	 */
	protected function should_handle_shipping_in_paypal( string $payment_source ): bool {
		$is_vaulting_enabled = $this->settings->has( 'vault_enabled' ) && $this->settings->get( 'vault_enabled' );

		if ( ! $this->handle_shipping_in_paypal ) {
			return false;
		}

		return ! $is_vaulting_enabled || $payment_source !== 'venmo';
	}
}
