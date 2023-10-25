<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Cart;
use WC_Order;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;

/**
 * Class ApplePayButton
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
	 * The response templates.
	 *
	 * @var ResponsesToApple
	 */
	private $response_templates;

	/**
	 * The old cart contents.
	 *
	 * @var array
	 * @psalm-suppress PropertyNotSetInConstructor
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
	 * Whether to reload the cart after the order is processed.
	 *
	 * @var bool
	 */
	protected $reload_cart = false;

	/**
	 * The module version.
	 *
	 * @var string
	 */
	private $version;
	/**
	 * The module URL.
	 *
	 * @var string
	 */
	private $module_url;
	/**
	 * The data to send to the ApplePay button script.
	 *
	 * @var DataToAppleButtonScripts
	 */
	private $script_data;
	/**
	 * The Settings status helper.
	 *
	 * @var SettingsStatus
	 */
	private $settings_status;

	/**
	 * PayPalPaymentMethod constructor.
	 *
	 * @param Settings                 $settings The settings.
	 * @param LoggerInterface          $logger The logger.
	 * @param OrderProcessor           $order_processor The Order processor.
	 * @param string                   $module_url The module URL.
	 * @param string                   $version The module version.
	 * @param DataToAppleButtonScripts $data The data to send to the ApplePay button script.
	 * @param SettingsStatus           $settings_status The settings status helper.
	 */
	public function __construct(
		Settings $settings,
		LoggerInterface $logger,
		OrderProcessor $order_processor,
		string $module_url,
		string $version,
		DataToAppleButtonScripts $data,
		SettingsStatus $settings_status
	) {
		$this->settings           = $settings;
		$this->response_templates = new ResponsesToApple();
		$this->logger             = $logger;
		$this->id                 = 'applepay';
		$this->method_title       = __( 'Apple Pay', 'woocommerce-paypal-payments' );
		$this->order_processor    = $order_processor;
		$this->module_url         = $module_url;
		$this->version            = $version;
		$this->script_data        = $data;
		$this->settings_status    = $settings_status;
	}

	/**
	 * Initializes the class hooks.
	 */
	public function initialize(): void {
		add_filter( 'ppcp_onboarding_options', array( $this, 'add_apple_onboarding_option' ), 10, 1 );
		add_filter(
			'ppcp_partner_referrals_option',
			function ( array $option ): array {
				if ( $option['valid'] ) {
					return $option;
				}
				if ( $option['field'] === 'ppcp-onboarding-apple' ) {
					$option['valid'] = true;
					$option['value'] = ( $option['value'] ? '1' : '' );
				}
				return $option;
			}
		);
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
		if ( ! apply_filters( 'woocommerce_paypal_payments_apple_pay_onboarding_option', false ) ) {
			return $options;
		}

		$checked = '';
		try {
			$onboard_with_apple = $this->settings->get( 'ppcp-onboarding-apple' );
			if ( $onboard_with_apple === '1' ) {
				$checked = 'checked';
			}
		} catch ( NotFoundException $exception ) {
			$checked = '';
		}

		return $options . '<li><label><input type="checkbox" id="ppcp-onboarding-apple" ' . $checked . ' data-onboarding-option="ppcp-onboarding-apple"> ' .
			__( 'Onboard with ApplePay', 'woocommerce-paypal-payments' ) . '
		</label></li>';

	}

	/**
	 * Adds all the Ajax actions to perform the whole workflow
	 */
	public function bootstrap_ajax_request(): void {
		add_action(
			'wp_ajax_' . PropertiesDictionary::VALIDATE,
			array( $this, 'validate' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::VALIDATE,
			array( $this, 'validate' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::CREATE_ORDER,
			array( $this, 'create_wc_order' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER,
			array( $this, 'create_wc_order' )
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
	 * Method to validate the merchant in the db flag
	 * On fail triggers and option that shows an admin notice showing the error
	 * On success removes such flag
	 */
	public function validate(): void {
		$applepay_request_data_object = $this->applepay_data_object_http();
		if ( ! $this->is_nonce_valid() ) {
			return;
		}
		$applepay_request_data_object->validation_data();
		$settings = $this->settings;
		$settings->set( 'applepay_validated', $applepay_request_data_object->validated_flag() );
		$settings->persist();
		wp_send_json_success();
	}
	/**
	 * Method to validate and update the shipping contact of the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
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
		try {
			$payment_details = $this->which_calculate_totals( $applepay_request_data_object );
			if ( ! is_array( $payment_details ) ) {
				$this->response_templates->response_with_data_errors(
					array(
						array(
							'errorCode' => 'addressUnserviceable',
							'message'   => __( 'Error processing cart', 'woocommerce-paypal-payments' ),
						),
					)
				);
				return;
			}
			$response = $this->response_templates->apple_formatted_response( $payment_details );
			$this->response_templates->response_success( $response );
		} catch ( \Exception $e ) {
			$this->response_templates->response_with_data_errors(
				array(
					array(
						'errorCode' => 'addressUnserviceable',
						'message'   => $e->getMessage(),
					),
				)
			);
		}
	}

	/**
	 * Method to validate and update the shipping method selected by the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
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
		try {
			$payment_details = $this->which_calculate_totals( $applepay_request_data_object );
			if ( ! is_array( $payment_details ) ) {
				$this->response_templates->response_with_data_errors(
					array(
						array(
							'errorCode' => 'addressUnserviceable',
							'message'   => __( 'Error processing cart', 'woocommerce-paypal-payments' ),
						),
					)
				);
				return;
			}
			$response = $this->response_templates->apple_formatted_response( $payment_details );
			$this->response_templates->response_success( $response );
		} catch ( \Exception $e ) {
			$this->response_templates->response_with_data_errors(
				array(
					array(
						'errorCode' => 'addressUnserviceable',
						'message'   => $e->getMessage(),
					),
				)
			);
		}
	}

	/**
	 * Method to create a WC order from the data received from the ApplePay JS
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new order data
	 *
	 * @throws \Exception When validation fails.
	 */
	public function create_wc_order(): void {
		$applepay_request_data_object = $this->applepay_data_object_http();
		//phpcs:disable WordPress.Security.NonceVerification
		$context = wc_clean( wp_unslash( $_POST['caller_page'] ?? '' ) );
		if ( ! is_string( $context ) ) {
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
		$applepay_request_data_object->order_data( $context );
		$this->update_posted_data( $applepay_request_data_object );
		if ( $context === 'product' ) {
			$cart_item_key = $this->prepare_cart( $applepay_request_data_object );
			$cart          = WC()->cart;
			$address       = $applepay_request_data_object->shipping_address();
			$this->calculate_totals_single_product(
				$cart,
				$address,
				$applepay_request_data_object->shipping_method()
			);
			if ( ! $cart_item_key ) {
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
			add_filter(
				'woocommerce_payment_successful_result',
				function ( array $result ) use ( $cart, $cart_item_key ) : array {
					if ( ! is_string( $cart_item_key ) ) {
						return $result;
					}
					$this->clear_current_cart( $cart, $cart_item_key );
					$this->reload_cart( $cart );
					return $result;
				}
			);
		}
		$this->add_addresses_to_order( $applepay_request_data_object );
		WC()->checkout()->process_checkout();
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
	protected function create_wc_countries(): \WC_Countries {
		return new \WC_Countries();
	}

	/**
	 * Selector between product detail and cart page calculations
	 *
	 * @param ApplePayDataObjectHttp $applepay_request_data_object The data object.
	 *
	 * @return array|bool
	 * @throws Exception If cannot be added to cart.
	 */
	protected function which_calculate_totals(
		$applepay_request_data_object
	) {
		$address = empty( $applepay_request_data_object->shipping_address() ) ? $applepay_request_data_object->simplified_contact() : $applepay_request_data_object->shipping_address();
		if ( $applepay_request_data_object->caller_page() === 'productDetail' ) {
			$cart_item_key = $this->prepare_cart( $applepay_request_data_object );
			$cart          = WC()->cart;

			$totals = $this->calculate_totals_single_product(
				$cart,
				$address,
				$applepay_request_data_object->shipping_method()
			);
			if ( is_string( $cart_item_key ) ) {
				$this->clear_current_cart( $cart, $cart_item_key );
				$this->reload_cart( $cart );
			}
			return $totals;
		}
		if ( $applepay_request_data_object->caller_page() === 'cart' ) {
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
	 * @param WC_Cart    $cart The cart.
	 * @param array      $customer_address customer address to use.
	 * @param array|null $shipping_method shipping method to use.
	 */
	protected function calculate_totals_single_product(
		$cart,
		$customer_address,
		$shipping_method = null
	): array {
		$results = array();
		try {
			// I just care about apple address details.
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
			return array();
		}
		return $results;
	}

	/**
	 * Sets the customer address with ApplePay details to perform correct
	 * calculations
	 * If no parameter passed then it resets the customer to shop details
	 *
	 * @param array $address customer address.
	 */
	protected function customer_address( array $address = array() ): void {
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
	 * @param WC_Cart    $cart WC Cart instance.
	 * @param array      $customer_address Customer address.
	 * @param array|null $shipping_method Shipping method.
	 * @param string     $shipping_method_id Shipping method id.
	 */
	protected function cart_shipping_methods(
		$cart,
		$customer_address,
		$shipping_method = null,
		$shipping_method_id = ''
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
	 * @param array $customer_address ApplePay address details.
	 * @param float $total Total amount of the cart.
	 *
	 * @return mixed|void|null
	 */
	protected function getShippingPackages( $customer_address, $total ) {
		// Packages array for storing 'carts'.
		$packages                                = array();
		$packages[0]['contents']                 = WC()->cart->cart_contents;
		$packages[0]['contents_cost']            = $total;
		$packages[0]['applied_coupons']          = WC()->session->applied_coupon;
		$packages[0]['destination']['country']   = $customer_address['country'] ?? '';
		$packages[0]['destination']['state']     = '';
		$packages[0]['destination']['postcode']  = $customer_address['postcode'] ?? '';
		$packages[0]['destination']['city']      = $customer_address['city'] ?? '';
		$packages[0]['destination']['address']   = '';
		$packages[0]['destination']['address_2'] = '';

		return apply_filters( 'woocommerce_cart_shipping_packages', $packages );
	}

	/**
	 * Returns the formatted results of the cart calculations
	 *
	 * @param WC_Cart $cart WC Cart object.
	 * @param array   $selected_shipping_method Selected shipping method.
	 * @param array   $shipping_methods_array Shipping methods array.
	 */
	protected function cart_calculation_results(
		$cart,
		$selected_shipping_method,
		$shipping_methods_array
	): array {
		$total = (float) $cart->get_total( 'edit' );
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
	 * @param array      $customer_address The customer address.
	 * @param array|null $shipping_method The shipping method.
	 */
	protected function calculate_totals_cart_page(
		array $customer_address,
		$shipping_method = null
	): array {

		$results = array();
		if ( WC()->cart->is_empty() ) {
			return array();
		}
		try {
			$shipping_methods_array   = array();
			$selected_shipping_method = array();
			// I just care about apple address details.
			$this->customer_address( $customer_address );
			$cart = WC()->cart;
			if ( $shipping_method ) {
				WC()->session->set(
					'chosen_shipping_methods',
					array( $shipping_method['identifier'] )
				);
			}

			if ( $cart->needs_shipping() ) {
				$shipping_method_id = $shipping_method['identifier'] ?? '';
				list(
					$shipping_methods_array, $selected_shipping_method
					)               = $this->cart_shipping_methods(
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

			$this->customer_address();
		} catch ( Exception $e ) {
			return array();
		}

		return $results;
	}

	/**
	 * Add address billing and shipping data to order
	 *
	 * @param ApplePayDataObjectHttp $applepay_request_data_object ApplePayDataObjectHttp.
	 */
	protected function add_addresses_to_order(
		ApplePayDataObjectHttp $applepay_request_data_object
	): void {
		add_action(
			'woocommerce_checkout_create_order',
			static function ( WC_Order $order, array $data ) use ( $applepay_request_data_object ) {
				if ( ! empty( $applepay_request_data_object->shipping_method() ) ) {
					$billing_address  = $applepay_request_data_object->billing_address();
					$shipping_address = $applepay_request_data_object->shipping_address();
					// apple puts email in shipping_address while we get it from WC's billing_address.
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
	protected function save_old_cart(): void {
		$cart = WC()->cart;
		if ( $cart->is_empty() ) {
			return;
		}
		$this->old_cart_contents = $cart->get_cart_contents();
		foreach ( $this->old_cart_contents as $cart_item_key => $value ) {
			$cart->remove_cart_item( $cart_item_key );
		}
		$this->reload_cart = true;
	}

	/**
	 * Reloads the previous cart contents
	 *
	 * @param WC_Cart $cart The cart to reload.
	 */
	protected function reload_cart( WC_Cart $cart ): void {
		if ( ! $this->reload_cart ) {
			return;
		}
		foreach ( $this->old_cart_contents as $cart_item_key => $value ) {
			$cart->restore_cart_item( $cart_item_key );
		}
	}

	/**
	 * Clear the current cart
	 *
	 * @param WC_Cart|null $cart The cart object.
	 * @param string       $cart_item_key The cart item key.
	 * @return void
	 */
	public function clear_current_cart( ?WC_Cart $cart, string $cart_item_key ): void {
		if ( ! $cart ) {
			return;
		}
		$cart->remove_cart_item( $cart_item_key );
		$this->customer_address();
	}

	/**
	 * Removes the old cart, saves it, and creates a new one
	 *
	 * @param ApplePayDataObjectHttp $applepay_request_data_object The request data object.
	 * @return bool | string The cart item key after adding to the new cart.
	 * @throws \Exception If cannot be added to cart.
	 */
	public function prepare_cart( ApplePayDataObjectHttp $applepay_request_data_object ): string {
		$this->save_old_cart();
		$cart = WC()->cart;
		return $cart->add_to_cart(
			(int) $applepay_request_data_object->product_id(),
			(int) $applepay_request_data_object->product_quantity()
		);
	}

	/**
	 * Update the posted data to match the Apple Pay request data
	 *
	 * @param ApplePayDataObjectHttp $applepay_request_data_object The Apple Pay request data.
	 */
	protected function update_posted_data( $applepay_request_data_object ): void {
		add_filter(
			'woocommerce_checkout_posted_data',
			function ( array $data ) use ( $applepay_request_data_object ): array {

				$data['payment_method']     = 'ppcp-gateway';
				$data['shipping_method']    = $applepay_request_data_object->shipping_method();
				$data['billing_first_name'] = $applepay_request_data_object->billing_address()['first_name'] ?? '';
				$data['billing_last_name']  = $applepay_request_data_object->billing_address()['last_name'] ?? '';
				$data['billing_company']    = $applepay_request_data_object->billing_address()['company'] ?? '';
				$data['billing_country']    = $applepay_request_data_object->billing_address()['country'] ?? '';
				$data['billing_address_1']  = $applepay_request_data_object->billing_address()['address_1'] ?? '';
				$data['billing_address_2']  = $applepay_request_data_object->billing_address()['address_2'] ?? '';
				$data['billing_city']       = $applepay_request_data_object->billing_address()['city'] ?? '';
				$data['billing_state']      = $applepay_request_data_object->billing_address()['state'] ?? '';
				$data['billing_postcode']   = $applepay_request_data_object->billing_address()['postcode'] ?? '';

				if ( ! empty( $applepay_request_data_object->shipping_method() ) ) {
					$data['billing_email']       = $applepay_request_data_object->shipping_address()['email'] ?? '';
					$data['billing_phone']       = $applepay_request_data_object->shipping_address()['phone'] ?? '';
					$data['shipping_first_name'] = $applepay_request_data_object->shipping_address()['first_name'] ?? '';
					$data['shipping_last_name']  = $applepay_request_data_object->shipping_address()['last_name'] ?? '';
					$data['shipping_company']    = $applepay_request_data_object->shipping_address()['company'] ?? '';
					$data['shipping_country']    = $applepay_request_data_object->shipping_address()['country'] ?? '';
					$data['shipping_address_1']  = $applepay_request_data_object->shipping_address()['address_1'] ?? '';
					$data['shipping_address_2']  = $applepay_request_data_object->shipping_address()['address_2'] ?? '';
					$data['shipping_city']       = $applepay_request_data_object->shipping_address()['city'] ?? '';
					$data['shipping_state']      = $applepay_request_data_object->shipping_address()['state'] ?? '';
					$data['shipping_postcode']   = $applepay_request_data_object->shipping_address()['postcode'] ?? '';
					$data['shipping_email']      = $applepay_request_data_object->shipping_address()['email'] ?? '';
					$data['shipping_phone']      = $applepay_request_data_object->shipping_address()['phone'] ?? '';
				}

				return $data;
			}
		);
	}

	/**
	 * Renders the Apple Pay button on the page
	 *
	 * @return bool
	 *
	 * @psalm-suppress RedundantCondition
	 */
	public function render(): bool {
		if ( ! $this->is_enabled() ) {
			return true;
		}

		$button_enabled_product  = $this->settings_status->is_smart_button_enabled_for_location( 'product' );
		$button_enabled_cart     = $this->settings_status->is_smart_button_enabled_for_location( 'cart' );
		$button_enabled_checkout = true;
		$button_enabled_payorder = true;
		$button_enabled_minicart = $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' );

		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( array $components ) {
				$components[] = 'applepay';
				return $components;
			}
		);
		if ( $button_enabled_product ) {
			$default_hookname   = 'woocommerce_paypal_payments_single_product_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_product', $default_hookname );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hookname;
			add_action(
				$render_placeholder,
				function () {
					$this->applepay_button();
				}
			);
		}
		if ( $button_enabled_cart ) {
			$default_hook_name  = 'woocommerce_paypal_payments_cart_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_cart_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					$this->applepay_button();
				}
			);
		}

		if ( $button_enabled_checkout ) {
			$default_hook_name  = 'woocommerce_paypal_payments_checkout_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_checkout_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					$this->applepay_button();
				},
				21
			);
		}
		if ( $button_enabled_payorder ) {
			$default_hook_name  = 'woocommerce_paypal_payments_payorder_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_payorder_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					$this->applepay_button();
				},
				21
			);
		}

		if ( $button_enabled_minicart ) {
			$default_hook_name  = 'woocommerce_paypal_payments_minicart_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_minicart_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					echo '<span id="applepay-container-minicart" class="ppcp-button-applepay ppcp-button-minicart"></span>';
				},
				21
			);
		}

		return true;
	}
	/**
	 * ApplePay button markup
	 */
	protected function applepay_button(): void {
		?>
		<div id="applepay-container">
			<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Checks if the module should load the script.
	 *
	 * @return bool
	 */
	public function should_load_script(): bool {
		return true;
	}

	/**
	 * Enqueues the scripts.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_register_script(
			'wc-ppcp-applepay',
			untrailingslashit( $this->module_url ) . '/assets/js/boot.js',
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'wc-ppcp-applepay' );

		$this->enqueue_styles();

		wp_localize_script(
			'wc-ppcp-applepay',
			'wc_ppcp_applepay',
			$this->script_data()
		);
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script( 'wc-ppcp-applepay' );
			}
		);
	}

	/**
	 * Enqueues styles.
	 */
	public function enqueue_styles(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_register_style(
			'wc-ppcp-applepay',
			untrailingslashit( $this->module_url ) . '/assets/css/styles.css',
			array(),
			$this->version
		);
		wp_enqueue_style( 'wc-ppcp-applepay' );
	}

	/**
	 * Returns the script data.
	 *
	 * @return array
	 */
	public function script_data(): array {
		return $this->script_data->apple_pay_script_data();
	}

	/**
	 * Returns true if the module is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		try {
			return $this->settings->has( 'applepay_button_enabled' ) && $this->settings->get( 'applepay_button_enabled' );
		} catch ( Exception $e ) {
			return false;
		}
	}
}
