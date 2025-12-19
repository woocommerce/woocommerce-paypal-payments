<?php

/**
 * The Pay upon invoice Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
/**
 * Class PayUponInvoiceGateway.
 */
class PayUponInvoiceGateway extends WC_Payment_Gateway
{
    use OrderMetaTrait;
    const ID = 'ppcp-pay-upon-invoice-gateway';
    /**
     * The order endpoint.
     *
     * @var PayUponInvoiceOrderEndpoint
     */
    protected $order_endpoint;
    /**
     * The purchase unit factory.
     *
     * @var PurchaseUnitFactory
     */
    protected $purchase_unit_factory;
    /**
     * The payment source factory.
     *
     * @var PaymentSourceFactory
     */
    protected $payment_source_factory;
    /**
     * The environment.
     *
     * @var Environment
     */
    protected $environment;
    /**
     * The transaction url provider.
     *
     * @var TransactionUrlProvider
     */
    protected $transaction_url_provider;
    /**
     * The logger interface.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * The PUI helper.
     *
     * @var PayUponInvoiceHelper
     */
    protected $pui_helper;
    /**
     * The checkout helper.
     *
     * @var CheckoutHelper
     */
    protected $checkout_helper;
    /**
     * The refund processor.
     *
     * @var RefundProcessor
     */
    protected $refund_processor;
    /**
     * The module URL
     *
     * @var string
     */
    private $module_url;
    /**
     * ID of the class extending the settings API. Used in option names.
     *
     * @var string
     */
    public $id;
    /**
     * Gateway title.
     *
     * @var string
     */
    public $method_title = '';
    /**
     * Gateway description.
     *
     * @var string
     */
    public $method_description = '';
    /**
     * Payment method title for the frontend.
     *
     * @var string
     */
    public $title;
    /**
     * Payment method description for the frontend.
     *
     * @var string
     */
    public $description;
    /**
     * Form option fields.
     *
     * @var array
     */
    public $form_fields = array();
    /**
     * Icon for the gateway.
     *
     * @var string
     */
    public $icon;
    /**
     * Supported features such as 'default_credit_card_form', 'refunds'.
     *
     * @var array
     */
    public $supports = array('products');
    /**
     * PayUponInvoiceGateway constructor.
     *
     * @param PayUponInvoiceOrderEndpoint $order_endpoint The order endpoint.
     * @param PurchaseUnitFactory         $purchase_unit_factory The purchase unit factory.
     * @param PaymentSourceFactory        $payment_source_factory The payment source factory.
     * @param Environment                 $environment The environment.
     * @param TransactionUrlProvider      $transaction_url_provider The transaction URL provider.
     * @param LoggerInterface             $logger The logger.
     * @param PayUponInvoiceHelper        $pui_helper The PUI helper.
     * @param CheckoutHelper              $checkout_helper The checkout helper.
     * @param bool                        $is_connected Whether the onboarding was completed.
     * @param RefundProcessor             $refund_processor The refund processor.
     * @param string                      $module_url The module URL.
     */
    public function __construct(PayUponInvoiceOrderEndpoint $order_endpoint, PurchaseUnitFactory $purchase_unit_factory, \WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSourceFactory $payment_source_factory, Environment $environment, TransactionUrlProvider $transaction_url_provider, LoggerInterface $logger, PayUponInvoiceHelper $pui_helper, CheckoutHelper $checkout_helper, bool $is_connected, RefundProcessor $refund_processor, string $module_url)
    {
        $this->id = self::ID;
        $this->method_title = __('Pay upon Invoice', 'woocommerce-paypal-payments');
        $this->method_description = __('Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.', 'woocommerce-paypal-payments');
        $gateway_settings = get_option('woocommerce_ppcp-pay-upon-invoice-gateway_settings');
        $this->title = $gateway_settings['title'] ?? $this->method_title;
        $this->description = $gateway_settings['description'] ?? __('Once you place an order, pay within 30 days. Our payment partner Ratepay will send you payment instructions.', 'woocommerce-paypal-payments');
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        $this->order_endpoint = $order_endpoint;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->payment_source_factory = $payment_source_factory;
        $this->logger = $logger;
        $this->environment = $environment;
        $this->transaction_url_provider = $transaction_url_provider;
        $this->pui_helper = $pui_helper;
        $this->checkout_helper = $checkout_helper;
        $this->module_url = $module_url;
        $this->icon = apply_filters('woocommerce_paypal_payments_pay_upon_invoice_gateway_icon', esc_url($this->module_url) . 'assets/images/ratepay.svg');
        if ($is_connected) {
            $this->supports = array('refunds');
        }
        $this->refund_processor = $refund_processor;
    }
    /**
     * Initialize the form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('Pay upon Invoice', 'woocommerce-paypal-payments'), 'default' => 'no', 'desc_tip' => \true, 'description' => __('Enable/Disable Pay upon Invoice payment gateway.', 'woocommerce-paypal-payments')), 'title' => array('title' => __('Title', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => $this->title, 'desc_tip' => \true, 'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments')), 'description' => array('title' => __('Description', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => $this->description, 'desc_tip' => \true, 'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments')), 'experience_context' => array('title' => __('Experience Context', 'woocommerce-paypal-payments'), 'type' => 'title', 'description' => __("Specify brand name, logo and customer service instructions to be presented on Ratepay's payment instructions.", 'woocommerce-paypal-payments')), 'brand_name' => array('title' => __('Brand name', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => get_bloginfo('name') ?? '', 'desc_tip' => \true, 'description' => __('Merchant name displayed in Ratepay\'s payment instructions. Should not exceed 127 characters.', 'woocommerce-paypal-payments'), 'maxlength' => 127, 'custom_attributes' => array('pattern' => '.{1,127}', 'autocomplete' => 'off', 'required' => '')), 'logo_url' => array('title' => __('Logo URL', 'woocommerce-paypal-payments'), 'type' => 'url', 'default' => '', 'desc_tip' => \true, 'description' => __('Logo to be presented on Ratepay\'s payment instructions.', 'woocommerce-paypal-payments'), 'custom_attributes' => array('pattern' => '.+', 'autocomplete' => 'off', 'required' => '')), 'customer_service_instructions' => array('title' => __('Customer service instructions', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => '', 'desc_tip' => \true, 'description' => __('Customer service instructions to be presented on Ratepay\'s payment instructions.', 'woocommerce-paypal-payments'), 'custom_attributes' => array('pattern' => '.+', 'autocomplete' => 'off', 'required' => '')));
    }
    /**
     * Processes the order.
     *
     * @param int $order_id The WC order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $wc_order = wc_get_order($order_id);
        // phpcs:disable WordPress.Security.NonceVerification
        $birth_date = wc_clean(wp_unslash($_POST['billing_birth_date'] ?? ''));
        $pay_for_order = wc_clean(wp_unslash($_GET['pay_for_order'] ?? ''));
        if ('true' === $pay_for_order) {
            if (!$this->checkout_helper->validate_birth_date($birth_date)) {
                wc_add_notice('Invalid birth date.', 'error');
                return array('result' => 'failure');
            }
        }
        $phone_number = wc_clean(wp_unslash($_POST['billing_phone'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification
        if ($phone_number) {
            $wc_order->set_billing_phone($phone_number);
            $wc_order->save();
        }
        $purchase_unit = $this->purchase_unit_factory->from_wc_order($wc_order);
        $payment_source = $this->payment_source_factory->from_wc_order($wc_order, $birth_date);
        try {
            $order = $this->order_endpoint->create(array($purchase_unit), $payment_source, $wc_order);
            $this->add_paypal_meta($wc_order, $order, $this->environment);
            $wc_order->update_status('on-hold', __('Awaiting Pay upon Invoice payment.', 'woocommerce-paypal-payments'));
            as_schedule_single_action(time() + 5 * MINUTE_IN_SECONDS, 'woocommerce_paypal_payments_check_pui_payment_captured', array('wc_order_id' => $order_id, 'order_id' => $order->id()));
            WC()->cart->empty_cart();
            return array('result' => 'success', 'redirect' => $this->get_return_url($wc_order));
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
            if (is_a($exception, PayPalApiException::class)) {
                $error = $exception->get_details($error);
            }
            $this->logger->error($error);
            wc_add_notice($error, 'error');
            $wc_order->update_status('failed', $error);
            return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
        }
    }
    /**
     * Process refund.
     *
     * @param  int    $order_id Order ID.
     * @param  float  $amount Refund amount.
     * @param  string $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
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
     * @param WC_Order $order WC order to get transaction url by.
     *
     * @return string
     */
    public function get_transaction_url($order): string
    {
        $this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base($order);
        return parent::get_transaction_url($order);
    }
}
