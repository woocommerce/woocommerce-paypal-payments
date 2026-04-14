<?php

/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Cart;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;
class ApplePayButton implements ButtonInterface
{
    use RequestHandlerTrait;
    private SettingsProvider $settings_provider;
    private PaymentSettings $payment_settings;
    private LoggerInterface $logger;
    private \WooCommerce\PayPalCommerce\Applepay\Assets\ResponsesToApple $response_templates;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private array $old_cart_contents;
    protected string $id;
    protected string $method_title;
    protected OrderProcessor $order_processor;
    protected bool $reload_cart = \false;
    private string $version;
    private AssetGetter $asset_getter;
    private \WooCommerce\PayPalCommerce\Applepay\Assets\DataToAppleButtonScripts $script_data;
    protected CartProductsHelper $cart_products;
    private Context $context;
    public function __construct(SettingsProvider $settings_provider, PaymentSettings $payment_settings, LoggerInterface $logger, OrderProcessor $order_processor, AssetGetter $asset_getter, string $version, \WooCommerce\PayPalCommerce\Applepay\Assets\DataToAppleButtonScripts $data, CartProductsHelper $cart_products, Context $context)
    {
        $this->settings_provider = $settings_provider;
        $this->payment_settings = $payment_settings;
        $this->response_templates = new \WooCommerce\PayPalCommerce\Applepay\Assets\ResponsesToApple();
        $this->logger = $logger;
        $this->id = 'applepay';
        $this->method_title = __('Apple Pay', 'woocommerce-paypal-payments');
        $this->order_processor = $order_processor;
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->script_data = $data;
        $this->cart_products = $cart_products;
        $this->context = $context;
    }
    public function initialize(): void
    {
    }
    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    public function bootstrap_ajax_request(): void
    {
        add_action('wp_ajax_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::VALIDATE, array($this, 'validate'));
        add_action('wp_ajax_nopriv_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::VALIDATE, array($this, 'validate'));
        add_action('wp_ajax_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::CREATE_ORDER, array($this, 'create_wc_order'));
        add_action('wp_ajax_nopriv_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::CREATE_ORDER, array($this, 'create_wc_order'));
        add_action('wp_ajax_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::UPDATE_SHIPPING_CONTACT, array($this, 'update_shipping_contact'));
        add_action('wp_ajax_nopriv_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::UPDATE_SHIPPING_CONTACT, array($this, 'update_shipping_contact'));
        add_action('wp_ajax_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::UPDATE_SHIPPING_METHOD, array($this, 'update_shipping_method'));
        add_action('wp_ajax_nopriv_' . \WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary::UPDATE_SHIPPING_METHOD, array($this, 'update_shipping_method'));
    }
    /**
     * Handles a validation notice from the front-end, which indicates that Apple Pay was
     * loaded. This notification verifies, that the domain verification was successful and
     * Apple Pay can be fully used.
     */
    public function validate(): void
    {
        $applepay_request_data_object = $this->applepay_data_object_http();
        if (!$this->is_nonce_valid()) {
            return;
        }
        $applepay_request_data_object->validation_data();
        $this->payment_settings->set_applepay_validated($applepay_request_data_object->validated_flag());
        $this->payment_settings->save();
        wp_send_json_success();
    }
    /**
     * Method to validate and update the shipping contact of the user
     * It updates the amount paying information if needed
     * On error returns an array of errors to be handled by the script
     * On success returns the new contact data
     */
    public function update_shipping_contact(): void
    {
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
        $is_allowed_selling_country = array_key_exists($user_country, $allowed_selling_countries);
        $is_allowed_shipping_country = array_key_exists($user_country, $allowed_shipping_countries);
        if (!$is_allowed_selling_country) {
            $this->response_templates->response_with_data_errors(array(array('errorCode' => 'addressUnserviceable')));
            return;
        }
        if ($applepay_request_data_object->need_shipping() && !$is_allowed_shipping_country) {
            $this->response_templates->response_with_data_errors(array(array('errorCode' => 'addressUnserviceable')));
            return;
        }
        try {
            $payment_details = $this->which_calculate_totals($applepay_request_data_object);
            if (!is_array($payment_details)) {
                $this->response_templates->response_with_data_errors(array(array('errorCode' => 'addressUnserviceable', 'message' => __('Error processing cart', 'woocommerce-paypal-payments'))));
                return;
            }
            $response = $this->response_templates->apple_formatted_response($payment_details);
            $this->response_templates->response_success($response);
        } catch (Exception $e) {
            $this->response_templates->response_with_data_errors(array(array('errorCode' => 'addressUnserviceable', 'message' => $e->getMessage())));
        }
    }
    /**
     * Method to validate and update the shipping method selected by the user
     * It updates the amount paying information if needed
     * On error returns an array of errors to be handled by the script
     * On success returns the new contact data
     */
    public function update_shipping_method(): void
    {
        $applepay_request_data_object = $this->applepay_data_object_http();
        if (!$this->is_nonce_valid()) {
            return;
        }
        $applepay_request_data_object->update_method_data();
        if ($applepay_request_data_object->has_errors()) {
            $this->response_templates->response_with_data_errors($applepay_request_data_object->errors());
        }
        try {
            $payment_details = $this->which_calculate_totals($applepay_request_data_object);
            if (!is_array($payment_details)) {
                $this->response_templates->response_with_data_errors(array(array('errorCode' => 'addressUnserviceable', 'message' => __('Error processing cart', 'woocommerce-paypal-payments'))));
                return;
            }
            $response = $this->response_templates->apple_formatted_response($payment_details);
            $this->response_templates->response_success($response);
        } catch (Exception $e) {
            $this->response_templates->response_with_data_errors(array(array('errorCode' => 'addressUnserviceable', 'message' => $e->getMessage())));
        }
    }
    /**
     * Method to create a WC order from the data received from the ApplePay JS
     * On error returns an array of errors to be handled by the script
     * On success returns the new order data
     *
     * @throws Exception When validation fails.
     */
    public function create_wc_order(): void
    {
        $applepay_request_data_object = $this->applepay_data_object_http();
        //phpcs:disable WordPress.Security.NonceVerification
        $context = wc_clean(wp_unslash($_POST['caller_page'] ?? ''));
        if (!is_string($context)) {
            $this->response_templates->response_with_data_errors(array(array('errorCode' => 'unableToProcess', 'message' => 'Unable to process the order')));
            return;
        }
        $applepay_request_data_object->order_data($context);
        if ($applepay_request_data_object->has_errors()) {
            $this->response_templates->response_with_data_errors($applepay_request_data_object->errors());
            return;
        }
        $this->update_posted_data($applepay_request_data_object);
        if ($context === 'product') {
            $cart_item_key = $this->prepare_cart($applepay_request_data_object);
            $cart = WC()->cart;
            $address = $applepay_request_data_object->shipping_address();
            $this->calculate_totals_single_product($cart, $address, $applepay_request_data_object->shipping_method());
            if (!$cart_item_key) {
                $this->response_templates->response_with_data_errors(array(array('errorCode' => 'unableToProcess', 'message' => 'Unable to process the order')));
            } else {
                add_filter('woocommerce_payment_successful_result', function (array $result) use ($cart, $cart_item_key): array {
                    $this->clear_current_cart($cart, $cart_item_key);
                    $this->reload_cart($cart);
                    return $result;
                });
            }
        }
        WC()->checkout()->process_checkout();
    }
    /**
     * Checks if the nonce in the data object is valid
     *
     * @return bool
     */
    protected function is_nonce_valid(): bool
    {
        $nonce = filter_input(\INPUT_POST, 'woocommerce-process-checkout-nonce', \FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$nonce) {
            return \false;
        }
        // Return value 1 indicates "valid nonce, generated in past 12 hours".
        // Return value 2 also indicated valid nonce, but older than 12 hours.
        return 1 === wp_verify_nonce($nonce, 'woocommerce-process_checkout');
    }
    /**
     * Data Object to collect and validate all needed data collected
     * through HTTP
     */
    protected function applepay_data_object_http(): \WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayDataObjectHttp
    {
        return new \WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayDataObjectHttp($this->logger);
    }
    /**
     * Returns a WC_Countries instance to check shipping
     *
     * @return \WC_Countries
     */
    protected function create_wc_countries(): \WC_Countries
    {
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
    protected function which_calculate_totals($applepay_request_data_object)
    {
        $address = empty($applepay_request_data_object->shipping_address()) ? $applepay_request_data_object->simplified_contact() : $applepay_request_data_object->shipping_address();
        if ($applepay_request_data_object->caller_page() === 'productDetail') {
            $cart_item_key = $this->prepare_cart($applepay_request_data_object);
            $cart = WC()->cart;
            $totals = $this->calculate_totals_single_product($cart, $address, $applepay_request_data_object->shipping_method());
            if ($cart_item_key) {
                $this->clear_current_cart($cart, $cart_item_key);
                $this->reload_cart($cart);
            }
            return $totals;
        }
        if ($applepay_request_data_object->caller_page() === 'cart') {
            return $this->calculate_totals_cart_page($address, $applepay_request_data_object->shipping_method());
        }
        return \false;
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
    protected function calculate_totals_single_product($cart, $customer_address, $shipping_method = null): array
    {
        $results = array();
        try {
            // I just care about apple address details.
            $shipping_method_id = '';
            $shipping_methods_array = array();
            $selected_shipping_method = array();
            $this->customer_address($customer_address);
            if ($shipping_method) {
                $shipping_method_id = $shipping_method['identifier'];
                WC()->session->set('chosen_shipping_methods', array($shipping_method_id));
            }
            if ($cart->needs_shipping()) {
                list($shipping_methods_array, $selected_shipping_method) = $this->cart_shipping_methods($cart, $customer_address, $shipping_method, $shipping_method_id);
            }
            $cart->calculate_shipping();
            $cart->calculate_fees();
            $cart->calculate_totals();
            $results = $this->cart_calculation_results($cart, $selected_shipping_method, $shipping_methods_array);
        } catch (Exception $exception) {
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
    protected function customer_address(array $address = array()): void
    {
        $base_location = wc_get_base_location();
        $shop_country_code = $base_location['country'];
        WC()->customer->set_shipping_country($address['country'] ?? $shop_country_code);
        WC()->customer->set_billing_country($address['country'] ?? $shop_country_code);
        WC()->customer->set_shipping_postcode($address['postcode'] ?? '');
        WC()->customer->set_shipping_city($address['city'] ?? '');
    }
    /**
     * Add shipping methods to cart to perform correct calculations
     *
     * @param WC_Cart    $cart WC Cart instance.
     * @param array      $customer_address Customer address.
     * @param array|null $shipping_method Shipping method.
     * @param string     $shipping_method_id Shipping method id.
     */
    protected function cart_shipping_methods($cart, $customer_address, $shipping_method = null, $shipping_method_id = ''): array
    {
        $shipping_methods_array = array();
        /**
         * The argument is defined only in docblock.
         *
         * @psalm-suppress InvalidScalarArgument
         */
        $shipping_methods = WC()->shipping->calculate_shipping($this->getShippingPackages($customer_address, $cart->get_total('edit')));
        $done = \false;
        foreach ($shipping_methods[0]['rates'] as $rate) {
            $shipping_methods_array[] = array('label' => $rate->get_label(), 'detail' => '', 'amount' => $rate->get_cost(), 'identifier' => $rate->get_id());
            if (!$done) {
                $done = \true;
                $shipping_method_id = $shipping_method ? $shipping_method_id : $rate->get_id();
                WC()->session->set('chosen_shipping_methods', array($shipping_method_id));
            }
        }
        $selected_shipping_method = $shipping_methods_array[0] ?? array();
        if ($shipping_method) {
            $selected_shipping_method = $shipping_method;
        }
        return array($shipping_methods_array, $selected_shipping_method);
    }
    /**
     * Sets shipping packages for correct calculations
     *
     * @param array $customer_address ApplePay address details.
     * @param float $total Total amount of the cart.
     *
     * @return mixed|void|null
     */
    protected function getShippingPackages($customer_address, $total)
    {
        // Packages array for storing 'carts'.
        $packages = array();
        $packages[0]['contents'] = WC()->cart->cart_contents;
        $packages[0]['contents_cost'] = $total;
        $packages[0]['applied_coupons'] = WC()->session->applied_coupon;
        $packages[0]['destination']['country'] = $customer_address['country'] ?? '';
        $packages[0]['destination']['state'] = '';
        $packages[0]['destination']['postcode'] = $customer_address['postcode'] ?? '';
        $packages[0]['destination']['city'] = $customer_address['city'] ?? '';
        $packages[0]['destination']['address'] = '';
        $packages[0]['destination']['address_2'] = '';
        return apply_filters('woocommerce_cart_shipping_packages', $packages);
    }
    /**
     * Returns the formatted results of the cart calculations
     *
     * @param WC_Cart $cart WC Cart object.
     * @param array   $selected_shipping_method Selected shipping method.
     * @param array   $shipping_methods_array Shipping methods array.
     */
    protected function cart_calculation_results($cart, $selected_shipping_method, $shipping_methods_array): array
    {
        $total = (float) $cart->get_total('edit');
        $total = round($total, 2);
        $discount_total = (float) $cart->get_discount_total();
        return array('subtotal' => $cart->get_subtotal(), 'discount' => $discount_total > 0 ? array('amount' => $discount_total, 'label' => __('Discount', 'woocommerce-paypal-payments')) : null, 'shipping' => array('amount' => $cart->needs_shipping() ? $cart->get_shipping_total() : null, 'label' => $cart->needs_shipping() ? $selected_shipping_method['label'] ?? null : null), 'shippingMethods' => $cart->needs_shipping() ? $shipping_methods_array : null, 'taxes' => $cart->get_total_tax(), 'total' => $total);
    }
    /**
     * Calculates totals for the cart page with the given information
     * If no shippingMethodId provided will return the first available shipping
     * method
     *
     * @param array      $customer_address The customer address.
     * @param array|null $shipping_method The shipping method.
     */
    protected function calculate_totals_cart_page(array $customer_address, $shipping_method = null): array
    {
        $results = array();
        if (WC()->cart->is_empty()) {
            return array();
        }
        try {
            $shipping_methods_array = array();
            $selected_shipping_method = array();
            // I just care about apple address details.
            $this->customer_address($customer_address);
            $cart = WC()->cart;
            if ($shipping_method) {
                WC()->session->set('chosen_shipping_methods', array($shipping_method['identifier']));
            }
            if ($cart->needs_shipping()) {
                $shipping_method_id = $shipping_method['identifier'] ?? '';
                list($shipping_methods_array, $selected_shipping_method) = $this->cart_shipping_methods($cart, $customer_address, $shipping_method, $shipping_method_id);
            }
            $cart->calculate_shipping();
            $cart->calculate_fees();
            $cart->calculate_totals();
            $results = $this->cart_calculation_results($cart, $selected_shipping_method, $shipping_methods_array);
            $this->customer_address();
        } catch (Exception $e) {
            return array();
        }
        return $results;
    }
    /**
     * Empty the cart to use for calculations
     * while saving its contents in a field
     */
    protected function save_old_cart(): void
    {
        $cart = WC()->cart;
        if ($cart->is_empty()) {
            return;
        }
        $this->old_cart_contents = $cart->get_cart_contents();
        foreach ($this->old_cart_contents as $cart_item_key => $value) {
            $cart->remove_cart_item($cart_item_key);
        }
        $this->reload_cart = \true;
    }
    /**
     * Reloads the previous cart contents
     *
     * @param WC_Cart $cart The cart to reload.
     */
    protected function reload_cart(WC_Cart $cart): void
    {
        if (!$this->reload_cart) {
            return;
        }
        foreach ($this->old_cart_contents as $cart_item_key => $value) {
            $cart->restore_cart_item($cart_item_key);
        }
    }
    /**
     * Clear the current cart
     *
     * @param WC_Cart|null $cart The cart object.
     * @param string       $cart_item_key The cart item key.
     * @return void
     */
    public function clear_current_cart(?WC_Cart $cart, string $cart_item_key): void
    {
        if (!$cart) {
            return;
        }
        $cart->remove_cart_item($cart_item_key);
        $this->customer_address();
    }
    /**
     * Removes the old cart, saves it, and creates a new one
     *
     * @throws Exception If it cannot be added to cart.
     * @param ApplePayDataObjectHttp $applepay_request_data_object The request data object.
     * @return string The cart item key after adding to the new cart.
     */
    public function prepare_cart(\WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayDataObjectHttp $applepay_request_data_object): string
    {
        $this->save_old_cart();
        $this->cart_products->set_cart(WC()->cart);
        $product = $this->cart_products->product_from_data(array('id' => (int) $applepay_request_data_object->product_id(), 'quantity' => (int) $applepay_request_data_object->product_quantity(), 'variations' => $applepay_request_data_object->product_variations(), 'extra' => $applepay_request_data_object->product_extra(), 'booking' => $applepay_request_data_object->product_booking()));
        $this->cart_products->add_products(array($product));
        return $this->cart_products->cart_item_keys()[0] ?? '';
    }
    /**
     * Update the posted data to match the Apple Pay request data
     *
     * @param ApplePayDataObjectHttp $applepay_request_data_object The Apple Pay request data.
     */
    protected function update_posted_data($applepay_request_data_object): void
    {
        // TODO : get checkout form data in here to fill more fields like: ensure billing email and phone are filled.
        add_filter('woocommerce_checkout_posted_data', function (array $data) use ($applepay_request_data_object): array {
            $data['payment_method'] = 'ppcp-gateway';
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
            $data['billing_email'] = $applepay_request_data_object->billing_address()['email'] ?? '';
            $data['billing_phone'] = $applepay_request_data_object->billing_address()['phone'] ?? '';
            // ApplePay doesn't send us a billing email or phone, use the shipping contacts instead.
            if (empty($data['billing_email'])) {
                $data['billing_email'] = $applepay_request_data_object->shipping_address()['email'] ?? '';
            }
            if (empty($data['billing_phone'])) {
                $data['billing_phone'] = $applepay_request_data_object->shipping_address()['phone'] ?? '';
            }
            if (!empty($applepay_request_data_object->shipping_method())) {
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
        });
    }
    /**
     * Renders the Apple Pay button on the page
     *
     * @return bool
     *
     * @psalm-suppress RedundantCondition
     */
    public function render(): bool
    {
        if (!$this->is_enabled()) {
            return \false;
        }
        add_filter('woocommerce_paypal_payments_sdk_components_hook', function (array $components) {
            $components[] = 'applepay';
            return $components;
        });
        $button_hooks = array(array('hook' => 'woocommerce_paypal_payments_single_product_button_render', 'filter' => 'woocommerce_paypal_payments_applepay_render_hook_product', 'callback' => fn() => $this->applepay_button()), array('hook' => 'woocommerce_paypal_payments_cart_button_render', 'filter' => 'woocommerce_paypal_payments_applepay_cart_button_render_hook', 'callback' => fn() => $this->applepay_button()), array('hook' => 'woocommerce_paypal_payments_checkout_button_render', 'filter' => 'woocommerce_paypal_payments_applepay_checkout_button_render_hook', 'callback' => function () {
            $this->applepay_button();
            $this->hide_gateway_until_eligible();
        }, 'priority' => 21), array('hook' => 'woocommerce_paypal_payments_payorder_button_render', 'filter' => 'woocommerce_paypal_payments_applepay_payorder_button_render_hook', 'callback' => function () {
            $this->applepay_button();
            $this->hide_gateway_until_eligible();
        }, 'priority' => 21), array('hook' => 'woocommerce_paypal_payments_minicart_button_render', 'filter' => 'woocommerce_paypal_payments_applepay_minicart_button_render_hook', 'callback' => fn() => print '<span id="ppc-button-applepay-container-minicart" class="ppcp-button-apm ppcp-button-applepay ppcp-button-minicart"></span>', 'priority' => 21));
        foreach ($button_hooks as $entry) {
            $hook = apply_filters($entry['filter'], $entry['hook']);
            $hook = is_string($hook) ? $hook : $entry['hook'];
            add_action($hook, $entry['callback'], $entry['priority'] ?? 21);
        }
        return \true;
    }
    /**
     * ApplePay button markup
     */
    protected function applepay_button(): void
    {
        ?>
		<div id="ppc-button-applepay-container" class="ppcp-button-apm ppcp-button-applepay">
			<?php 
        wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
        ?>
		</div>
		<?php 
    }
    /**
     * Outputs an inline CSS style that hides the Apple Pay gateway (on Classic Checkout).
     * The style is removed by `ApplepayButton.js` once the eligibility of the payment method
     * is confirmed.
     *
     * @return void
     */
    protected function hide_gateway_until_eligible(): void
    {
        ?>
		<style data-hide-gateway="ppcp-applepay">.wc_payment_method.payment_method_ppcp-applepay{display:none}</style>
		<?php 
    }
    /**
     * Enqueues the scripts.
     *
     * @return void
     */
    public function enqueue(): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        wp_register_script('wc-ppcp-applepay', $this->asset_getter->get_asset_url('boot.js'), array(), $this->version, \true);
        wp_enqueue_script('wc-ppcp-applepay');
        $this->enqueue_styles();
        wp_localize_script('wc-ppcp-applepay', 'wc_ppcp_applepay', $this->script_data());
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_script('wc-ppcp-applepay');
        });
    }
    /**
     * Enqueues styles.
     */
    public function enqueue_styles(): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        wp_register_style('wc-ppcp-applepay', $this->asset_getter->get_asset_url('styles.css'), array(), $this->version);
        wp_enqueue_style('wc-ppcp-applepay');
    }
    /**
     * Enqueues scripts for admin.
     */
    public function enqueue_admin(): void
    {
        wp_register_script('wc-ppcp-applepay-admin', $this->asset_getter->get_asset_url('boot-admin.js'), array(), $this->version, \true);
        wp_enqueue_script('wc-ppcp-applepay-admin');
        wp_localize_script('wc-ppcp-applepay-admin', 'wc_ppcp_applepay_admin', $this->script_data_for_admin());
    }
    /**
     * Enqueues styles for admin.
     */
    public function enqueue_admin_styles(): void
    {
        wp_register_style('wc-ppcp-applepay-admin', $this->asset_getter->get_asset_url('styles.css'), array(), $this->version);
        wp_enqueue_style('wc-ppcp-applepay-admin');
    }
    /**
     * Returns the script data.
     *
     * @return array
     */
    public function script_data(): array
    {
        return $this->script_data->apple_pay_script_data();
    }
    /**
     * Returns the admin script data.
     *
     * @return array
     */
    public function script_data_for_admin(): array
    {
        return $this->script_data->apple_pay_script_data_for_admin();
    }
    /**
     * Returns true if the module is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool
    {
        if (!$this->settings_provider->applepay_enabled()) {
            return \false;
        }
        $methods = $this->settings_provider->button_styling($this->context->context())->methods;
        return in_array(ApplePayGateway::ID, $methods, \true);
    }
}
