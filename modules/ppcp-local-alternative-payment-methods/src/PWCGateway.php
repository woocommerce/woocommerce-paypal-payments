<?php

/**
 * Pay with Crypto payment gateway.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Exception;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
/**
 * Class PWCGateway
 */
class PWCGateway extends WC_Payment_Gateway
{
    public const ID = 'ppcp-pwc';
    /**
     * PayPal Orders endpoint.
     *
     * @var Orders
     */
    private Orders $orders_endpoint;
    /**
     * Purchase unit factory.
     *
     * @var PurchaseUnitFactory
     */
    private PurchaseUnitFactory $purchase_unit_factory;
    /**
     * The Refund Processor.
     *
     * @var RefundProcessor
     */
    private RefundProcessor $refund_processor;
    /**
     * Shipping preference factory.
     *
     * @var ShippingPreferenceFactory
     */
    private ShippingPreferenceFactory $shipping_preference_factory;
    /**
     * Service able to provide transaction url for an order.
     *
     * @var TransactionUrlProvider
     */
    protected TransactionUrlProvider $transaction_url_provider;
    /**
     * The ExperienceContextBuilder.
     *
     * @var ExperienceContextBuilder
     */
    protected ExperienceContextBuilder $experience_context_builder;
    /**
     * PWCGateway constructor.
     *
     * @param AssetGetter               $wc_gateway_module_asset_getter
     * @param Orders                    $orders_endpoint PayPal Orders endpoint.
     * @param PurchaseUnitFactory       $purchase_unit_factory Purchase unit factory.
     * @param RefundProcessor           $refund_processor The Refund Processor.
     * @param ShippingPreferenceFactory $shipping_preference_factory Shipping preference factory.
     * @param TransactionUrlProvider    $transaction_url_provider Service providing transaction view URL based on order.
     * @param ExperienceContextBuilder  $experience_context_builder The ExperienceContextBuilder.
     */
    public function __construct(AssetGetter $wc_gateway_module_asset_getter, Orders $orders_endpoint, PurchaseUnitFactory $purchase_unit_factory, RefundProcessor $refund_processor, ShippingPreferenceFactory $shipping_preference_factory, TransactionUrlProvider $transaction_url_provider, ExperienceContextBuilder $experience_context_builder)
    {
        $this->id = self::ID;
        $this->supports = array('refunds', 'products');
        $this->init_apm_defaults();
        // TODO: Change to the official svg asset when it's available: Something like https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_crypto_color.svg.
        $this->icon = $wc_gateway_module_asset_getter->get_static_asset_url('images/pwc.svg');
        $this->init_form_fields();
        $this->init_settings();
        $this->init_apm_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        $this->orders_endpoint = $orders_endpoint;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->refund_processor = $refund_processor;
        $this->shipping_preference_factory = $shipping_preference_factory;
        $this->transaction_url_provider = $transaction_url_provider;
        $this->experience_context_builder = $experience_context_builder;
    }
    /**
     * Initialize the form fields.
     */
    public function init_form_fields(): void
    {
        $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('Pay with Crypto', 'woocommerce-paypal-payments'), 'default' => 'no', 'desc_tip' => \true, 'description' => __('Enable/Disable Pay with Crypto payment gateway.', 'woocommerce-paypal-payments')), 'title' => array('title' => __('Title', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => $this->title, 'desc_tip' => \true, 'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments')), 'description' => array('title' => __('Description', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => $this->description, 'desc_tip' => \true, 'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments')));
    }
    /**
     * Processes the order.
     *
     * @param int $order_id The WC order ID.
     * @return array
     * @throws Exception When payer action URL is not found in PayPal response.
     */
    public function process_payment($order_id): array
    {
        $wc_order = wc_get_order($order_id);
        if (!is_a($wc_order, \WC_Order::class)) {
            wc_add_notice(__('Order not found.', 'woocommerce-paypal-payments'), 'error');
            return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
        }
        if ($wc_order->get_currency() !== 'USD') {
            wc_add_notice(__('Crypto payments are only available for USD orders.', 'woocommerce-paypal-payments'), 'error');
            return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
        }
        try {
            $purchase_unit = $this->purchase_unit_factory->from_wc_order($wc_order);
            $amount = $purchase_unit->amount()->to_array();
            $base_return_url = $wc_order->get_checkout_order_received_url();
            $experience_context = $this->experience_context_builder->with_custom_return_url($base_return_url)->with_custom_cancel_url(add_query_arg('cancelled', 'true', $base_return_url))->with_current_locale()->build()->to_array();
            $request_body = array('processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL', 'intent' => 'CAPTURE', 'purchase_units' => array(array('reference_id' => $purchase_unit->reference_id(), 'amount' => array('currency_code' => $amount['currency_code'], 'value' => $amount['value']), 'custom_id' => (string) $wc_order->get_id(), 'invoice_id' => $purchase_unit->invoice_id())), 'payment_source' => array('crypto' => array('country_code' => $wc_order->get_billing_country(), 'name' => array('given_name' => $wc_order->get_billing_first_name(), 'surname' => $wc_order->get_billing_last_name()), 'experience_context' => $experience_context)));
            $response = $this->orders_endpoint->create($request_body);
            $body = json_decode($response['body']);
            $wc_order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $body->id);
            $wc_order->update_status('on-hold', __('Awaiting Pay with Crypto payment confirmation.', 'woocommerce-paypal-payments'));
            $wc_order->save();
            $payer_action_url = $this->get_payer_action_url($body);
            if (empty($payer_action_url)) {
                throw new Exception(__('No payer action URL found in PayPal response.', 'woocommerce-paypal-payments'));
            }
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => esc_url($payer_action_url));
        } catch (Exception $exception) {
            wc_add_notice(__('Payment failed. Please try again.', 'woocommerce-paypal-payments'), 'error');
            return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
        }
    }
    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return bool True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order = wc_get_order($order_id);
        if (!is_a($order, \WC_Order::class)) {
            return \false;
        }
        return $this->refund_processor->process($order, (float) $amount, (string) $reason);
    }
    /**
     * Return transaction url for this gateway and given order.
     *
     * @param \WC_Order $order WC order to get transaction url by.
     *
     * @return string
     */
    public function get_transaction_url($order): string
    {
        $this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base($order);
        return parent::get_transaction_url($order);
    }
    /**
     * Extract payer-action URL from PayPal order response.
     *
     * @param object $order_response Parsed PayPal order response.
     * @return string
     */
    private function get_payer_action_url($order_response): string
    {
        if (!isset($order_response->links)) {
            return '';
        }
        foreach ($order_response->links as $link) {
            if (isset($link->rel) && $link->rel === 'payer-action') {
                return $link->href ?? '';
            }
        }
        return '';
    }
    /**
     * Initialize APM gateway defaults from centralized definition.
     */
    private function init_apm_defaults(): void
    {
        $defaults = PaymentMethodsDefinition::get_apm_defaults()[self::ID];
        $this->method_title = $defaults['method_title'];
        $this->method_description = $defaults['method_description'];
    }
    /**
     * Load saved settings and override defaults.
     */
    private function init_apm_settings(): void
    {
        $defaults = PaymentMethodsDefinition::get_apm_defaults()[self::ID];
        $this->title = $this->get_option('title', $defaults['title']);
        $this->description = $this->get_option('description', $defaults['description']);
    }
}
