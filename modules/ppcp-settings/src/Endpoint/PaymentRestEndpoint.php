<?php

/**
 * REST endpoint to manage the payment methods page.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDependenciesDefinition;
/**
 * REST controller for the "Payment Methods" settings tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class PaymentRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'payment';
    /**
     * The settings instance.
     *
     * @var PaymentSettings
     */
    protected PaymentSettings $payment_settings;
    /**
     * The payment method details.
     *
     * @var PaymentMethodsDefinition
     */
    protected PaymentMethodsDefinition $payment_methods_definition;
    /**
     * The payment method dependencies.
     *
     * @var PaymentMethodsDependenciesDefinition
     */
    protected PaymentMethodsDependenciesDefinition $payment_methods_dependencies;
    /**
     * Field mapping for request to profile transformation.
     *
     * @var array
     */
    private array $field_map = array('paypal_show_logo' => array('js_name' => 'paypalShowLogo', 'sanitize' => 'to_boolean'), 'cardholder_name' => array('js_name' => 'cardholderName', 'sanitize' => 'to_boolean'), 'fastlane_display_watermark' => array('js_name' => 'fastlaneDisplayWatermark', 'sanitize' => 'to_boolean'));
    /**
     * Constructor.
     *
     * @param PaymentSettings                      $payment_settings           The settings instance.
     * @param PaymentMethodsDefinition             $payment_methods_definition Payment Method details.
     * @param PaymentMethodsDependenciesDefinition $payment_methods_dependencies       The payment method dependencies.
     */
    public function __construct(PaymentSettings $payment_settings, PaymentMethodsDefinition $payment_methods_definition, PaymentMethodsDependenciesDefinition $payment_methods_dependencies)
    {
        $this->payment_settings = $payment_settings;
        $this->payment_methods_definition = $payment_methods_definition;
        $this->payment_methods_dependencies = $payment_methods_dependencies;
    }
    /**
     * Field mapping for request to profile transformation.
     *
     * @return array[]
     */
    protected function gateways(): array
    {
        $methods = $this->payment_methods_definition->get_definitions();
        // Add dependency information to the methods.
        return $this->payment_methods_dependencies->add_dependency_info_to_methods($methods);
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * GET wc/v3/wc_paypal/payment
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_details'), 'permission_callback' => array($this, 'check_permission')));
        /**
         * POST wc/v3/wc_paypal/payment
         * {
         *     [gateway_id]: {
         *         enabled
         *         title
         *         description
         *     }
         * }
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_details'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Returns all payment methods details.
     *
     * @return WP_REST_Response The current payment methods details.
     */
    public function get_details(): WP_REST_Response
    {
        $gateway_settings = array();
        $all_payment_methods = $this->gateways();
        // First extract __meta if present.
        if (isset($all_payment_methods['__meta'])) {
            $gateway_settings['__meta'] = $all_payment_methods['__meta'];
        }
        foreach ($all_payment_methods as $key => $payment_method) {
            // Skip the __meta key as we've already handled it.
            if ($key === '__meta') {
                continue;
            }
            $gateway_settings[$key] = array('id' => $payment_method['id'], 'title' => $payment_method['title'], 'description' => $payment_method['description'], 'enabled' => $payment_method['enabled'], 'icon' => $payment_method['icon'], 'itemTitle' => $payment_method['itemTitle'], 'itemDescription' => $payment_method['itemDescription'], 'warningMessages' => $payment_method['warningMessages']);
            if (isset($payment_method['fields'])) {
                $gateway_settings[$key]['fields'] = $payment_method['fields'];
            }
            if (isset($payment_method['depends_on_payment_methods'])) {
                $gateway_settings[$key]['depends_on_payment_methods'] = $payment_method['depends_on_payment_methods'];
            }
            if (isset($payment_method['depends_on_payment_methods_values'])) {
                $gateway_settings[$key]['depends_on_payment_methods_values'] = $payment_method['depends_on_payment_methods_values'];
            }
            if (isset($payment_method['depends_on_settings'])) {
                $gateway_settings[$key]['depends_on_settings'] = $payment_method['depends_on_settings'];
            }
        }
        $gateway_settings['paypalShowLogo'] = $this->payment_settings->get_paypal_show_logo();
        $gateway_settings['cardholderName'] = $this->payment_settings->get_cardholder_name();
        $gateway_settings['fastlaneDisplayWatermark'] = $this->payment_settings->get_fastlane_display_watermark();
        return $this->return_success(apply_filters('woocommerce_paypal_payments_payment_methods', $gateway_settings));
    }
    /**
     * Updates payment methods details based on the request.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response The updated payment methods details.
     */
    public function update_details(WP_REST_Request $request): WP_REST_Response
    {
        $request_data = $request->get_params();
        $all_methods = $this->gateways();
        foreach ($all_methods as $key => $value) {
            $new_data = $request_data[$key] ?? null;
            if (!$new_data) {
                continue;
            }
            if (isset($new_data['enabled'])) {
                $this->payment_settings->toggle_method_state($key, $new_data['enabled']);
            }
            if (isset($new_data['title'])) {
                $this->payment_settings->set_method_title($key, sanitize_text_field($new_data['title']));
            }
            if (isset($new_data['description'])) {
                $this->payment_settings->set_method_description($key, wp_kses_post($new_data['description']));
            }
        }
        $wp_data = $this->sanitize_for_wordpress($request->get_params(), $this->field_map);
        $this->payment_settings->from_array($wp_data);
        $this->payment_settings->save();
        return $this->get_details();
    }
}
