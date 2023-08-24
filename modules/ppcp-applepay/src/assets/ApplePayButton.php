<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use Psr\Log\LoggerInterface;
use WC_Cart;
use WC_Checkout;
use WC_Order;
use WC_Session_Handler;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Session\MemoryWcSession;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;

/**
 * Class PayPalPaymentMethod
 */
class ApplePayButton implements ButtonInterface {
	use RequestHandlerTrait;
	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;
	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var ResponsesToApple
	 */
	private $response_templates;

	/**
	 * @var array The old cart contents.
	 */
	private $old_cart_contents;

	/**
	 * The method id.
	 *
	 * @var string
	 */
	protected $id;
	/**
	 * The method title.
	 *
	 * @var string
	 */
	protected $method_title;
	/**
	 * The processor for orders.
	 *
	 * @var OrderProcessor
	 */
	protected $order_processor;
	/**
	 * @var bool Whether to reload the cart after the order is processed.
	 */
	protected $reload_cart = false;
	private $version;
	/**
	 * @var string
	 */
	private $module_url;
	/**
	 * @var string
	 */
	private $script_data;

	/**
	 * PayPalPaymentMethod constructor.
	 *
	 * @param Settings $settings The settings.
	 * @param LoggerInterface $logger The logger.
	 * @param OrderProcessor           $order_processor The Order processor.
	 */
	public function __construct(
		Settings                 $settings,
		LoggerInterface          $logger,
		OrderProcessor           $order_processor,
		string                   $module_url,
		string                   $version,
		DataToAppleButtonScripts $data
	) {
		$this->settings    = $settings;
		$this->response_templates = new ResponsesToApple();
		$this->logger             = $logger;
		$this->id 			   = 'applepay';
		$this->method_title    = __( 'Apple Pay', 'woocommerce-paypal-payments' );
		$this->order_processor = $order_processor;
		$this->module_url      = $module_url;
		$this->version         = $version;
		$this->script_data = $data;
	}

	/**
	 * Initializes the class hooks.
	 */
	public function initialize(): void {
		add_filter( 'ppcp_onboarding_options', array( $this, 'add_apple_onboarding_option' ), 10, 1 );
		add_filter(
			'ppcp_partner_referrals_data',
			function ( array $data ): array {
				try {
					$onboard_with_apple = $this->settings->get( 'ppcp-onboarding-apple' );
					if ( $onboard_with_apple !== '1' ) {
						return $data;
					}
				} catch ( NotFoundException $exception ) {
					return $data;
				}

				if ( in_array( 'PPCP', $data['products'], true ) ) {
					$data['products'][] = 'PAYMENT_METHODS';
				} elseif ( in_array( 'EXPRESS_CHECKOUT', $data['products'], true ) ) {
					$data['products'][0] = 'PAYMENT_METHODS';
				}
				$data['capabilities'][] = 'APPLE_PAY';
				$nonce                  = $data['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['seller_nonce'];
				$data['operations'][]   = array(
					'operation'                  => 'API_INTEGRATION',
					'api_integration_preference' => array(
						'rest_api_integration' => array(
							'integration_method'  => 'PAYPAL',
							'integration_type'    => 'THIRD_PARTY',
							'third_party_details' => array(
								'features'     => array(
									'PAYMENT',
									'REFUND',
								),
								'seller_nonce' => $nonce,
							),
						),
					),
				);

				return $data;
			}
		);
	}

	/**
	 * Adds the ApplePay onboarding option.
	 *
	 * @param string $options The options.
	 *
	 * @return string
	 */
	public function add_apple_onboarding_option( $options ): string {
		$checked = '';
		try {
			$onboard_with_apple = $this->settings->get( 'ppcp-onboarding-apple' );
			if ( $onboard_with_apple === '1' ) {
				$checked = 'checked';
			}
		} catch ( NotFoundException $exception ) {
			$checked = '';
		}

		return $options . '<li><label><input type="checkbox" id="ppcp-onboarding-apple" ' . $checked . '> ' .
			__( 'Onboard with ApplePay', 'woocommerce-paypal-payments' ) . '
		</label></li>';

	}

	/**
	 * Adds all the Ajax actions to perform the whole workflow
	 */
	public function bootstrap_ajax_request(): void {
		add_action(
			'wp_ajax_' . PropertiesDictionary::CREATE_ORDER,
			array( $this, 'create_wc_order' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER,
			array( $this, 'create_wc_order' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::CREATE_ORDER_CART,
			array( $this, 'create_wc_order_from_cart' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER_CART,
			array( $this, 'create_wc_order_from_cart' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::UPDATE_SHIPPING_CONTACT,
			array( $this, 'update_shipping_contact' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::UPDATE_SHIPPING_CONTACT,
			array( $this, 'update_shipping_contact' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::UPDATE_SHIPPING_METHOD,
			array( $this, 'update_shipping_method' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::UPDATE_SHIPPING_METHOD,
			array( $this, 'update_shipping_method' )
		);
	}

	/**
	 * Method to validate and update the shipping contact of the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
	 * @throws \Exception
	 */
	public function update_shipping_contact(): void {
		$applepay_request_data_object = $this->applepay_data_object_http();
		if ( ! $this->is_nonce_valid() ) {
			return;
		}
		$applepay_request_data_object->update_contact_data();
		if ( $applepay_request_data_object->has_errors() ) {
			$this->response_templates->response_with_data_errors( $applepay_request_data_object->errors() );
			return;
		}

		if ( ! class_exists( 'WC_Countries' ) ) {
			return;
		}

		$countries                  = $this->create_wc_countries();
		$allowed_selling_countries  = $countries->get_allowed_countries();
		$allowed_shipping_countries = $countries->get_shipping_countries();
		$user_country               = $applepay_request_data_object->simplified_contact()['country'];
		$is_allowed_selling_country = array_key_exists(
			$user_country,
			$allowed_selling_countries
		);

		$is_allowed_shipping_country = array_key_exists(
			$user_country,
			$allowed_shipping_countries
		);
		$product_need_shipping       = $applepay_request_data_object->need_shipping();

		if ( ! $is_allowed_selling_country ) {
			$this->response_templates->response_with_data_errors(
				array( array( 'errorCode' => 'addressUnserviceable' ) )
			);
			return;
		}
		if ( $product_need_shipping && ! $is_allowed_shipping_country ) {
			$this->response_templates->response_with_data_errors(
				array( array( 'errorCode' => 'addressUnserviceable' ) )
			);
			return;
		}
		$cart_item_key = $this->prepare_cart($applepay_request_data_object);
		$cart = WC()->cart;
		$payment_details = $this->which_calculate_totals( $cart, $applepay_request_data_object );
		$this->clear_current_cart($cart, $cart_item_key);
		$this->reload_cart( $cart );
		$response        = $this->response_templates->apple_formatted_response( $payment_details );
		$this->response_templates->response_success( $response );
	}

	/**
	 * Method to validate and update the shipping method selected by the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
	 * @throws \Exception
	 */
	public function update_shipping_method(): void {
		$applepay_request_data_object = $this->applepay_data_object_http();
		if ( ! $this->is_nonce_valid() ) {
			return;
		}
		$applepay_request_data_object->update_method_data();
		if ( $applepay_request_data_object->has_errors() ) {
			$this->response_templates->response_with_data_errors( $applepay_request_data_object->errors() );
		}
		$cart_item_key = $this->prepare_cart($applepay_request_data_object);
		$cart = WC()->cart;
		$payment_details = $this->which_calculate_totals( $cart, $applepay_request_data_object );
		$this->clear_current_cart($cart, $cart_item_key);
		$this->reload_cart( $cart );
		$response       = $this->response_templates->apple_formatted_response( $payment_details );
		$this->response_templates->response_success( $response );
	}

	/**
	 * Method to create a WC order from the data received from the ApplePay JS
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new order data
	 * @throws \Exception
	 */
	public function create_wc_order() {
		//$this->response_after_successful_result();
		$applepay_request_data_object = $this->applepay_data_object_http();
		$applepay_request_data_object->order_data('productDetail');
		$this->update_posted_data($applepay_request_data_object);
		$cart_item_key = $this->prepare_cart($applepay_request_data_object);
		$cart = WC()->cart;
		$this->which_calculate_totals($cart, $applepay_request_data_object );
		if (! $cart_item_key) {
			$this->response_templates->response_with_data_errors(
				array(
					array(
						'errorCode' => 'unableToProcess',
						'message'   => 'Unable to process the order',
					),
				)
			);
			return;
		}
		$this->add_addresses_to_order($applepay_request_data_object);
		//add_action('woocommerce_checkout_order_processed', array($this, 'process_order_as_paid'), 10, 3);
		add_filter('woocommerce_payment_successful_result', function (array $result) use ($cart, $cart_item_key) : array {
			$this->clear_current_cart($cart, $cart_item_key);
			$this->reload_cart( $cart );
			return $result;
		});
		WC()->checkout()->process_checkout();
	}

	/**
	 * Method to create a WC order from the data received from the ApplePay JS
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new order data
	 */
	public function create_wc_order_from_cart(): void {
	}


	/**
	 * Checks if the nonce in the data object is valid
	 *
	 * @return bool|int
	 */
	protected function is_nonce_valid(): bool {
		$nonce = filter_input( INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $nonce ) {
			return false;
		}
		return wp_verify_nonce(
			$nonce,
			'woocommerce-process_checkout'
		) === 1;
	}

	/**
	 * Data Object to collect and validate all needed data collected
	 * through HTTP
	 */
	protected function applepay_data_object_http(): ApplePayDataObjectHttp {
		return new ApplePayDataObjectHttp( $this->logger );
	}

	/**
	 * Returns a WC_Countries instance to check shipping
	 *
	 * @return \WC_Countries
	 */
	protected function create_wc_countries() {
		return new \WC_Countries();
	}

	/**
	 * Selector between product detail and cart page calculations
	 *
	 * @param $applepay_request_data_object
	 *
	 * @return array|bool
	 */
	protected function which_calculate_totals(
		$cart,
		$applepay_request_data_object
	) {
		$address = $applepay_request_data_object->shipping_address() ?? $applepay_request_data_object->simplified_contact();
		if ( $applepay_request_data_object->caller_page === 'productDetail' ) {
			if (! assert($cart instanceof WC_Cart)) {
				return false;
			}
			return $this->calculate_totals_single_product(
				$cart,
				$address,
				$applepay_request_data_object->shipping_method()
			);
		}
		if ( $applepay_request_data_object->caller_page === 'cart' ) {
			return $this->calculate_totals_cart_page(
				$address,
				$applepay_request_data_object->shipping_method()
			);
		}
		return false;
	}

	/**
	 * Calculates totals for the product with the given information
	 * Saves the previous cart to reload it after calculations
	 * If no shippingMethodId provided will return the first available shipping
	 * method
	 *
	 * @param      $product_id
	 * @param      $product_quantity
	 * @param      $customer_address
	 * @param null             $shipping_method
	 */
	protected function calculate_totals_single_product(
		$cart,
		$customer_address,
		$shipping_method = null
	): array {
		$results     = array();
		try {
			// I just care about apple address details
			$shipping_method_id       = '';
			$shipping_methods_array   = array();
			$selected_shipping_method = array();
			$this->customer_address( $customer_address );
			if ( $shipping_method ) {
				$shipping_method_id = $shipping_method['identifier'];
				WC()->session->set(
					'chosen_shipping_methods',
					array( $shipping_method_id )
				);
			}
			if ( $cart->needs_shipping() ) {
				list(
					$shipping_methods_array, $selected_shipping_method
					) = $this->cart_shipping_methods(
						$cart,
						$customer_address,
						$shipping_method,
						$shipping_method_id
					);
			}
			$cart->calculate_shipping();
			$cart->calculate_fees();
			$cart->calculate_totals();

			$results = $this->cart_calculation_results(
				$cart,
				$selected_shipping_method,
				$shipping_methods_array
			);
		} catch ( Exception $exception ) {
		}
		return $results;
	}

	/**
	 * Sets the customer address with ApplePay details to perform correct
	 * calculations
	 * If no parameter passed then it resets the customer to shop details
	 */
	protected function customer_address( array $address = array() ) {
		$base_location     = wc_get_base_location();
		$shop_country_code = $base_location['country'];
		WC()->customer->set_shipping_country(
			$address['country'] ?? $shop_country_code
		);
		WC()->customer->set_billing_country(
			$address['country'] ?? $shop_country_code
		);
		WC()->customer->set_shipping_postcode(
			$address['postcode'] ?? $shop_country_code
		);
		WC()->customer->set_shipping_city(
			$address['city'] ?? $shop_country_code
		);
	}

	/**
	 * Add shipping methods to cart to perform correct calculations
	 *
	 * @param $cart
	 * @param $customer_address
	 * @param $shipping_method
	 * @param $shipping_method_id
	 */
	protected function cart_shipping_methods(
		$cart,
		$customer_address,
		$shipping_method,
		$shipping_method_id
	): array {

		$shipping_methods_array = array();
		$shipping_methods       = WC()->shipping->calculate_shipping(
			$this->getShippingPackages(
				$customer_address,
				$cart->get_total( 'edit' )
			)
		);
		$done                   = false;
		foreach ( $shipping_methods[0]['rates'] as $rate ) {
			$shipping_methods_array[] = array(
				'label'      => $rate->get_label(),
				'detail'     => '',
				'amount'     => $rate->get_cost(),
				'identifier' => $rate->get_id(),
			);
			if ( ! $done ) {
				$done               = true;
				$shipping_method_id = $shipping_method ? $shipping_method_id
					: $rate->get_id();
				WC()->session->set(
					'chosen_shipping_methods',
					array( $shipping_method_id )
				);
			}
		}

		$selected_shipping_method = $shipping_methods_array[0];
		if ( $shipping_method ) {
			$selected_shipping_method = $shipping_method;
		}

		return array( $shipping_methods_array, $selected_shipping_method );
	}

	/**
	 * Sets shipping packages for correct calculations
	 *
	 * @param $customer_address
	 * @param $total
	 *
	 * @return mixed|void|null
	 */
	protected function getShippingPackages( $customer_address, $total ) {
		// Packages array for storing 'carts'
		$packages                                = array();
		$packages[0]['contents']                 = WC()->cart->cart_contents;
		$packages[0]['contents_cost']            = $total;
		$packages[0]['applied_coupons']          = WC()->session->applied_coupon;
		$packages[0]['destination']['country']   = $customer_address['country'];
		$packages[0]['destination']['state']     = '';
		$packages[0]['destination']['postcode']  = $customer_address['postcode'];
		$packages[0]['destination']['city']      = $customer_address['city'];
		$packages[0]['destination']['address']   = '';
		$packages[0]['destination']['address_2'] = '';

		return apply_filters( 'woocommerce_cart_shipping_packages', $packages );
	}

	/**
	 * Returns the formatted results of the cart calculations
	 *
	 * @param $cart
	 * @param $selected_shipping_method
	 * @param $shipping_methods_array
	 */
	protected function cart_calculation_results(
		$cart,
		$selected_shipping_method,
		$shipping_methods_array
	): array {
		$total = $cart->get_total( 'edit' );
		$total = round( $total, 2 );
		return array(
			'subtotal'        => $cart->get_subtotal(),
			'shipping'        => array(
				'amount' => $cart->needs_shipping()
					? $cart->get_shipping_total() : null,
				'label'  => $cart->needs_shipping()
					? $selected_shipping_method['label'] : null,
			),

			'shippingMethods' => $cart->needs_shipping()
				? $shipping_methods_array : null,
			'taxes'           => $cart->get_total_tax(),
			'total'           => $total,
		);
	}

	/**
	 * Calculates totals for the cart page with the given information
	 * If no shippingMethodId provided will return the first available shipping
	 * method
	 *
	 * @param      $customer_address
	 * @param null             $shipping_method
	 */
	protected function calculate_totals_cart_page(
		$customer_address = null,
		$shipping_method = null
	): array {

		$results = array();
		if ( WC()->cart->is_empty() ) {
			return array();
		}
		try {
			$shipping_methods_array   = array();
			$selected_shipping_method = array();
			// I just care about apple address details
			$this->customer_address( $customer_address );
			$cart = WC()->cart;
			if ( $shipping_method ) {
				WC()->session->set(
					'chosen_shipping_methods',
					array( $shipping_method['identifier'] )
				);
			}

			if ( $cart->needs_shipping() ) {
				list(
					$shipping_methods_array, $selected_shipping_method
					) = $this->cart_shipping_methods(
						$cart,
						$customer_address,
						$shipping_method,
						$shipping_method['identifier']
					);
			}
			$cart->calculate_shipping();
			$cart->calculate_fees();
			$cart->calculate_totals();

			$results = $this->cart_calculation_results(
				$cart,
				$selected_shipping_method,
				$shipping_methods_array
			);

			$this->customer_address();
		} catch ( Exception $e ) {
		}

		return $results;
	}

	/**
	 * Add address billing and shipping data to order
	 *
	 * @param ApplePayDataObjectHttp $applepay_request_data_object
	 * @param                        $order
	 */
	protected function add_addresses_to_order(
		ApplePayDataObjectHttp $applepay_request_data_object
	) {
		add_action(
			'woocommerce_checkout_create_order',
			static function ( $order, $data ) use ( $applepay_request_data_object ) {
				if ( $applepay_request_data_object->shipping_method() !== null ) {
					$billing_address  = $applepay_request_data_object->billing_address();
					$shipping_address = $applepay_request_data_object->shipping_address();
					// apple puts email in shipping_address while we get it from WC's billing_address
					$billing_address['email'] = $shipping_address['email'];
					$billing_address['phone'] = $shipping_address['phone'];

					$order->set_address( $billing_address, 'billing' );
					$order->set_address( $shipping_address, 'shipping' );
				}
			},
			10,
			2
		);
	}
	/**
	 * Empty the cart to use for calculations
	 * while saving its contents in a field
	 */
	protected function save_old_cart() {
		$cart = WC()->cart;
		if ( $cart->is_empty() ||  ! assert($cart instanceof WC_Cart)) {
			return;
		}
		$this->old_cart_contents = $cart->get_cart_contents();
		foreach ( $this->old_cart_contents as $cart_item_key => $value ) {
			$cart->remove_cart_item( $cart_item_key );
		}
		$this->reload_cart = true;
	}

	/**
	 * @param WC_Cart $cart
	 */
	protected function reload_cart( WC_Cart $cart ): void {
		if ( ! $this->reload_cart ) {
			return;
		}
		foreach ( $this->old_cart_contents as $cart_item_key => $value ) {
			$cart->restore_cart_item( $cart_item_key );
		}
	}

	protected function response_after_successful_result(): void {
		add_filter(
			'woocommerce_payment_successful_result',
			function ( $result, $order_id ) {
				if (
					isset( $result['result'] )
					&& 'success' === $result['result']
				) {
					$this->response_templates->response_success(
						$this->response_templates->authorization_result_response(
							'STATUS_SUCCESS',
							$order_id
						)
					);
				} else {
					wp_send_json_error(
						$this->response_templates->authorization_result_response(
							'STATUS_FAILURE',
							0,
							array( array( 'errorCode' => 'unknown' ) )
						)
					);
				}
				return $result;
			},
			10,
			2
		);
	}

	/**
	 * @param WC_Cart|null $cart
	 * @param $cart_item_key
	 * @return void
	 */
	public function clear_current_cart(?WC_Cart $cart, $cart_item_key): void
	{
		$cart->remove_cart_item($cart_item_key);
		$this->customer_address();
	}

	/**
	 * Removes the old cart, saves it, and creates a new one
	 * @param ApplePayDataObjectHttp $applepay_request_data_object
	 * @return bool | string The cart item key after adding to the new cart
	 * @throws \Exception
	 */
	public function prepare_cart(ApplePayDataObjectHttp $applepay_request_data_object): string
	{
		$this->save_old_cart();
		$cart = WC()->cart;
		return $cart->add_to_cart(
			(int) $applepay_request_data_object->product_id(),
			(int) $applepay_request_data_object->product_quantity());
	}

	public function process_order_as_paid($order_id): void
	{
		$order = wc_get_order($order_id);
		if (!assert($order instanceof WC_Order)) {
			return;
		}
		$order->payment_complete();
		wc_reduce_stock_levels($order_id);
		$order->save();
	}

	protected function update_posted_data( $applepay_request_data_object )
	{
		add_filter(
			'woocommerce_checkout_posted_data',
			function ($data) use ($applepay_request_data_object) {

				$data['payment_method'] = "ppcp-gateway";
				$data['shipping_method'] = $applepay_request_data_object->shipping_method();
				$data['billing_first_name'] = $applepay_request_data_object->billing_address()['first_name'] ?? '';
				$data['billing_last_name'] = $applepay_request_data_object->billing_address()['last_name'] ?? '';
				$data['billing_company'] = $applepay_request_data_object->billing_address()['company'] ?? '';
				$data['billing_country'] = $applepay_request_data_object->billing_address()['country'] ?? '';
				$data['billing_address_1'] = $applepay_request_data_object->billing_address()['address_1'] ?? '';
				$data['billing_address_2'] = $applepay_request_data_object->billing_address()['address_2'] ?? '';
				$data['billing_city'] = $applepay_request_data_object->billing_address()['city'] ?? '';
				$data['billing_state'] = $applepay_request_data_object->billing_address()['state'] ?? '';
				$data['billing_postcode'] = $applepay_request_data_object->billing_address()['postcode'] ?? '';


				if ( $applepay_request_data_object->shipping_method() !== null ) {
					$data['billing_email'] = $applepay_request_data_object->shipping_address()['email'] ?? '';
					$data['billing_phone'] = $applepay_request_data_object->shipping_address()['phone'] ?? '';
					$data['shipping_first_name'] = $applepay_request_data_object->shipping_address()['first_name'] ?? '';
					$data['shipping_last_name'] = $applepay_request_data_object->shipping_address()['last_name'] ?? '';
					$data['shipping_company'] = $applepay_request_data_object->shipping_address()['company'] ?? '';
					$data['shipping_country'] = $applepay_request_data_object->shipping_address()['country'] ?? '';
					$data['shipping_address_1'] = $applepay_request_data_object->shipping_address()['address_1'] ?? '';
					$data['shipping_address_2'] = $applepay_request_data_object->shipping_address()['address_2'] ?? '';
					$data['shipping_city'] = $applepay_request_data_object->shipping_address()['city'] ?? '';
					$data['shipping_state'] = $applepay_request_data_object->shipping_address()['state'] ?? '';
					$data['shipping_postcode'] = $applepay_request_data_object->shipping_address()['postcode'] ?? '';
					$data['shipping_email'] = $applepay_request_data_object->shipping_address()['email'] ?? '';
					$data['shipping_phone'] = $applepay_request_data_object->shipping_address()['phone'] ?? '';
				}

				return $data;
			}
		);
	}

	public function render_buttons(): bool
	{
		$button_enabled_product = $this->settings->has( 'applepay_button_enabled_product' ) ? $this->settings->get( 'applepay_button_enabled_product' ) : false;
		$button_enabled_cart    = $this->settings->has( 'applepay_button_enabled_cart' ) ? $this->settings->get( 'applepay_button_enabled_cart' ) : false;

		if ( $button_enabled_product ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_product', 'woocommerce_after_add_to_cart_form' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_after_add_to_cart_form';
			add_action(
				$render_placeholder,
				function () {
					$this->apple_pay_direct_button();
				}
			);
		}
		if ( $button_enabled_cart ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_cart', 'woocommerce_cart_totals_after_order_total' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_cart_totals_after_order_total';
			add_action(
				$render_placeholder,
				function () {
					$this->apple_pay_direct_button();
				}
			);
		}
		return true;
	}
	/**
	 * ApplePay button markup
	 */
	protected function apple_pay_direct_button(): void {
		?>
		<div class="ppc-button-wrapper">
			<div id="applepay-container">
				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
			</div>
		</div>
		<?php
	}

	public function should_load_script(): bool
	{
		return true;
	}

	public function enqueue(): void
	{
		wp_register_script(
			'wc-ppcp-applepay',
			untrailingslashit( $this->module_url ) . '/assets/js/applePayDirect.js',
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'wc-ppcp-applepay' );

		wp_register_style(
			'wc-ppcp-applepay',
			untrailingslashit( $this->module_url ) . '/assets/css/styles.css',
			array(),
			$this->version
		);
		wp_enqueue_style( 'wc-ppcp-applepay' );

		wp_localize_script(
			'wc-ppcp-applepay',
			'wc_ppcp_applepay',
			$this->script_data()
		);
	}

	public function script_data(): array
	{
		return $this->script_data->apple_pay_script_data();
	}
}
