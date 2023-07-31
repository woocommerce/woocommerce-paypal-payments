<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayPalPaymentMethod
 */
class ApplepayPaymentMethod {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $plugin_settings;
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
	private $old_cart_contents;

	/**
	 * PayPalPaymentMethod constructor.
	 *
	 * @param Settings $plugin_settings The settings.
	 */
	public function __construct(
		Settings $plugin_settings,
		LoggerInterface $logger
	) {
		$this->plugin_settings = $plugin_settings;
		$this->response_templates = new ResponsesToApple();
		$this->logger = $logger;
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
					$onboard_with_apple = $this->plugin_settings->get( 'ppcp-onboarding-apple' );
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
				$nonce = $data['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['seller_nonce'];
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
								'seller_nonce' => $nonce
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
			$onboard_with_apple = $this->plugin_settings->get( 'ppcp-onboarding-apple' );
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
	 */
	public function update_shipping_contact(): void {
		$applepay_request_data_object = $this->applepay_data_object_http();
		if (!$this->is_nonce_valid()) {
			return;
		}
		$applepay_request_data_object->update_contact_data();
		if ($applepay_request_data_object->has_errors()) {
			$this->response_templates->response_with_data_errors($applepay_request_data_object->errors());
			return;
		}

		if (!class_exists('WC_Countries')) {
			return;
		}

		$countries = $this->create_wc_countries();
		$allowed_selling_countries = $countries->get_allowed_countries();
		$allowed_shipping_countries = $countries->get_shipping_countries();
		$user_country = $applepay_request_data_object->simplified_contact()['country'];
		$is_allowed_selling_country = array_key_exists(
			$user_country,
			$allowed_selling_countries
		);

		$is_allowed_shipping_country = array_key_exists(
			$user_country,
			$allowed_shipping_countries
		);
		$product_need_shipping = $applepay_request_data_object->need_shipping();

		if (!$is_allowed_selling_country) {
			$this->response_templates->response_with_data_errors(
				[['errorCode' => 'addressUnserviceable']]
			);
			return;
		}
		if ($product_need_shipping && !$is_allowed_shipping_country) {
			$this->response_templates->response_with_data_errors(
				[['errorCode' => 'addressUnserviceable']]
			);
			return;
		}

		$payment_details = $this->which_calculate_totals($applepay_request_data_object);
		$response = $this->response_templates->apple_formatted_response($payment_details);
		$this->response_templates->response_success($response);
	}

	/**
	 * Method to validate and update the shipping method selected by the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
	 */
	public function update_shipping_method(): void {
		$applepay_request_data_object = $this->applepay_data_object_http();
		if (!$this->is_nonce_valid()) {
			return;
		}
		$applepay_request_data_object->update_method_data();
		if ($applepay_request_data_object->has_errors()) {
			$this->response_templates->response_with_data_errors($applepay_request_data_object->errors());
		}
		$paymentDetails = $this->which_calculate_totals($applepay_request_data_object);
		$response = $this->response_templates->apple_formatted_response($paymentDetails);
		$this->response_templates->response_success($response);
	}

	/**
	 * Method to create a WC order from the data received from the ApplePay JS
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new order data
	 */
	public function create_wc_order(): void {
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
	protected function applepay_data_object_http(): ApplePayDataObjectHttp
	{
		return new ApplePayDataObjectHttp($this->logger);
	}

	/**
	 * Returns a WC_Countries instance to check shipping
	 *
	 * @return \WC_Countries
	 */
	protected function create_wc_countries()
	{
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
		$applepay_request_data_object
	) {

		if ($applepay_request_data_object->caller_page === 'productDetail') {
			return $this->calculate_totals_single_product(
				$applepay_request_data_object->product_id(),
				$applepay_request_data_object->product_quantity(),
				$applepay_request_data_object->simplified_contact(),
				$applepay_request_data_object->shipping_method()
			);
		}
		if ($applepay_request_data_object->caller_page === 'cart') {
			return $this->calculate_totals_cart_page(
				$applepay_request_data_object->simplified_contact(),
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
	 * @param null $shipping_method
	 */
	protected function calculate_totals_single_product(
		$product_id,
		$product_quantity,
		$customer_address,
		$shipping_method = null
	): array {

		$results = [];
		$reload_cart = false;
		if (!WC()->cart->is_empty()) {
			$old_cart_contents = WC()->cart->get_cart_contents();
			foreach (array_keys($old_cart_contents) as $cart_item_key) {
				WC()->cart->remove_cart_item($cart_item_key);
			}
			$reload_cart = true;
		}
		try {
			//I just care about apple address details
			$shipping_method_id = '';
			$shipping_methods_array = [];
			$selected_shipping_method = [];
			$this->customer_address($customer_address);
			$cart = WC()->cart;
			if ($shipping_method) {
				$shipping_method_id = $shipping_method['identifier'];
				WC()->session->set(
					'chosen_shipping_methods',
					[$shipping_method_id]
				);
			}
			$cart_item_key = $cart->add_to_cart($product_id, $product_quantity);
			if ($cart->needs_shipping()) {
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

			$cart->remove_cart_item($cart_item_key);
			$this->customer_address();
			if ($reload_cart) {
				foreach (array_keys($old_cart_contents) as $cart_item_key) {
					$cart->restore_cart_item($cart_item_key);
				}
			}
		} catch (Exception $exception) {
		}
		return $results;
	}

	/**
	 * Sets the customer address with ApplePay details to perform correct
	 * calculations
	 * If no parameter passed then it resets the customer to shop details
	 */
	protected function customer_address(array $address = [])
	{
		$base_location = wc_get_base_location();
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

		$shipping_methods_array = [];
		$shipping_methods = WC()->shipping->calculate_shipping(
			$this->getShippingPackages(
				$customer_address,
				$cart->get_total('edit')
			)
		);
		$done = false;
		foreach ($shipping_methods[0]['rates'] as $rate) {
			$shipping_methods_array[] = [
				"label" => $rate->get_label(),
				"detail" => "",
				"amount" => $rate->get_cost(),
				"identifier" => $rate->get_id(),
			];
			if (!$done) {
				$done = true;
				$shipping_method_id = $shipping_method ? $shipping_method_id
					: $rate->get_id();
				WC()->session->set(
					'chosen_shipping_methods',
					[$shipping_method_id]
				);
			}
		}

		$selected_shipping_method = $shipping_methods_array[0];
		if ($shipping_method) {
			$selected_shipping_method = $shipping_method;
		}

		return [$shipping_methods_array, $selected_shipping_method];
	}

	/**
	 * Sets shipping packages for correct calculations
	 * @param $customer_address
	 * @param $total
	 *
	 * @return mixed|void|null
	 */
	protected function getShippingPackages($customer_address, $total)
	{
		// Packages array for storing 'carts'
		$packages = [];
		$packages[0]['contents'] = WC()->cart->cart_contents;
		$packages[0]['contents_cost'] = $total;
		$packages[0]['applied_coupons'] = WC()->session->applied_coupon;
		$packages[0]['destination']['country'] = $customer_address['country'];
		$packages[0]['destination']['state'] = '';
		$packages[0]['destination']['postcode'] = $customer_address['postcode'];
		$packages[0]['destination']['city'] = $customer_address['city'];
		$packages[0]['destination']['address'] = '';
		$packages[0]['destination']['address_2'] = '';

		return apply_filters('woocommerce_cart_shipping_packages', $packages);
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
		$total = $cart->get_total('edit');
		$total = round($total, 2);
		return [
			'subtotal' => $cart->get_subtotal(),
			'shipping' => [
				'amount' => $cart->needs_shipping()
					? $cart->get_shipping_total() : null,
				'label' => $cart->needs_shipping()
					? $selected_shipping_method['label'] : null,
			],

			'shippingMethods' => $cart->needs_shipping()
				? $shipping_methods_array : null,
			'taxes' => $cart->get_total_tax(),
			'total' => $total,
		];
	}

	/**
	 * Calculates totals for the cart page with the given information
	 * If no shippingMethodId provided will return the first available shipping
	 * method
	 *
	 * @param      $customer_address
	 * @param null $shipping_method
	 */
	protected function calculate_totals_cart_page(
		$customer_address = null,
		$shipping_method = null
	): array {

		$results = [];
		if (WC()->cart->is_empty()) {
			return [];
		}
		try {
			$shipping_methods_array = [];
			$selected_shipping_method = [];
			//I just care about apple address details
			$this->customer_address($customer_address);
			$cart = WC()->cart;
			if ($shipping_method) {
				WC()->session->set(
					'chosen_shipping_methods',
					[$shipping_method['identifier']]
				);
			}

			if ($cart->needs_shipping()) {
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
		} catch (Exception $e) {
		}

		return $results;
	}

	/**
	 * Add address billing and shipping data to order
	 *
	 * @param ApplePayDataObjectHttp $applepay_request_data_object
	 * @param                        $order
	 *
	 */
	protected function addAddressesToOrder(
		ApplePayDataObjectHttp $applepay_request_data_object
	) {

		add_action(
			'woocommerce_checkout_create_order',
			static function ($order, $data) use ($applepay_request_data_object) {
				if ($applepay_request_data_object->shipping_method() !== null) {
					$billing_address = $applepay_request_data_object->billing_address();
					$shipping_address = $applepay_request_data_object->shipping_address();
					//apple puts email in shipping_address while we get it from WC's billing_address
					$billing_address['email'] = $shipping_address['email'];
					$billing_address['phone'] = $shipping_address['phone'];

					$order->set_address($billing_address, 'billing');
					$order->set_address($shipping_address, 'shipping');
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
	protected function empty_current_cart()
	{
		foreach ($this->old_cart_contents as $cart_item_key => $value) {
			WC()->cart->remove_cart_item($cart_item_key);
		}
		$this->reload_cart = true;
	}

	/**
	 * @param WC_Cart $cart
	 */
	protected function reload_cart(WC_Cart $cart): void
	{
		foreach ($this->old_cart_contents as $cart_item_key => $value) {
			$cart->restore_cart_item($cart_item_key);
		}
	}

	protected function response_after_successful_result(): void
	{
		add_filter(
			'woocommerce_payment_successful_result',
			function ($result, $order_id) {
				if (
					isset($result['result'])
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
							[['errorCode' => 'unknown']]
						)
					);
				}
				return $result;
			},
			10,
			2
		);
	}
}
