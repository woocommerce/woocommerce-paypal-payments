<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Exception;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
class OXXOGateway extends WC_Payment_Gateway
{
    public const ID = 'ppcp-oxxo-gateway';
    private Orders $orders_endpoint;
    private PurchaseUnitFactory $purchase_unit_factory;
    private RefundProcessor $refund_processor;
    protected TransactionUrlProvider $transaction_url_provider;
    protected ExperienceContextBuilder $experience_context_builder;
    public function __construct(Orders $orders_endpoint, PurchaseUnitFactory $purchase_unit_factory, RefundProcessor $refund_processor, TransactionUrlProvider $transaction_url_provider, ExperienceContextBuilder $experience_context_builder, AssetGetter $asset_getter)
    {
        $this->id = self::ID;
        $this->supports = array('refunds', 'products');
        $this->init_apm_defaults();
        $this->icon = $asset_getter->get_static_asset_url('images/oxxo.svg');
        $this->init_form_fields();
        $this->init_settings();
        $this->init_apm_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        $this->orders_endpoint = $orders_endpoint;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->refund_processor = $refund_processor;
        $this->transaction_url_provider = $transaction_url_provider;
        $this->experience_context_builder = $experience_context_builder;
    }
    public function init_form_fields(): void
    {
        $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('OXXO', 'woocommerce-paypal-payments'), 'default' => 'no', 'desc_tip' => \true, 'description' => __('Enable/Disable OXXO payment gateway.', 'woocommerce-paypal-payments')), 'title' => array('title' => __('Title', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => $this->title, 'desc_tip' => \true, 'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments')), 'description' => array('title' => __('Description', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => $this->description, 'desc_tip' => \true, 'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments')));
    }
    /**
     * @param int $order_id The WC order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $wc_order = wc_get_order($order_id);
        if (!$wc_order instanceof WC_Order) {
            return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
        }
        $wc_order->update_status('pending', __('Awaiting for the buyer to complete the payment.', 'woocommerce-paypal-payments'));
        $purchase_unit = $this->purchase_unit_factory->from_wc_order($wc_order);
        $amount = $purchase_unit->amount()->to_array();
        $request_body = array('intent' => 'CAPTURE', 'purchase_units' => array(array('reference_id' => $purchase_unit->reference_id(), 'amount' => array('currency_code' => $amount['currency_code'], 'value' => $amount['value']), 'custom_id' => $purchase_unit->custom_id(), 'invoice_id' => $purchase_unit->invoice_id())));
        try {
            $response = $this->orders_endpoint->create($request_body);
            $body = json_decode($response['body']);
            $request_body = array('payment_source' => array('oxxo' => array('name' => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(), 'email' => $wc_order->get_billing_email(), 'country_code' => $wc_order->get_billing_country(), 'experience_context' => $this->experience_context_builder->with_order_return_urls($wc_order)->build()->with_locale('en-MX')->to_array())), 'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL');
            $response = $this->orders_endpoint->confirm_payment_source($request_body, $body->id);
            $body = json_decode($response['body']);
            $payer_action = '';
            foreach ($body->links as $link) {
                if ($link->rel === 'payer-action') {
                    $payer_action = $link->href;
                    break;
                }
            }
            WC()->cart->empty_cart();
            $wc_order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $body->id);
            $wc_order->update_meta_data('ppcp_oxxo_payer_action', $payer_action);
            $wc_order->save_meta_data();
            return array('result' => 'success', 'redirect' => esc_url($payer_action));
        } catch (Exception $exception) {
            $wc_order->update_status('failed', $exception->getMessage());
            return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
        }
    }
    /**
     * @param int    $order_id Order ID.
     * @param float  $amount Refund amount.
     * @param string $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return \false;
        }
        return $this->refund_processor->process($order, (float) $amount, (string) $reason);
    }
    public function get_transaction_url($order): string
    {
        $this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base($order);
        return parent::get_transaction_url($order);
    }
    private function init_apm_defaults(): void
    {
        $defaults = PaymentMethodsDefinition::get_apm_defaults()[self::ID];
        $this->method_title = $defaults['method_title'];
        $this->method_description = $defaults['method_description'];
    }
    private function init_apm_settings(): void
    {
        $defaults = PaymentMethodsDefinition::get_apm_defaults()[self::ID];
        $this->title = $this->get_option('title', $defaults['title']);
        $this->description = $this->get_option('description', $defaults['description'] ?? '');
    }
}
