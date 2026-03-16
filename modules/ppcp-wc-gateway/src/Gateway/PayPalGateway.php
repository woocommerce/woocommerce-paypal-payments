<?php

/**
 * The PayPal Payment Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Exception\PayPalOrderMissingException;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CapturePayPalPayment;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Class PayPalGateway
 */
class PayPalGateway extends \WC_Payment_Gateway
{
    use \WooCommerce\PayPalCommerce\WcGateway\Gateway\ProcessPaymentTrait;
    use FreeTrialHandlerTrait;
    use OrderMetaTrait;
    use TransactionIdHandlingTrait;
    use PaymentsStatusHandlingTrait;
    public const ID = 'ppcp-gateway';
    public const INTENT_META_KEY = '_ppcp_paypal_intent';
    public const ORDER_ID_META_KEY = '_ppcp_paypal_order_id';
    public const ORDER_PAYMENT_MODE_META_KEY = '_ppcp_paypal_payment_mode';
    public const ORDER_PAYMENT_SOURCE_META_KEY = '_ppcp_paypal_payment_source';
    public const ORDER_PAYER_EMAIL_META_KEY = '_ppcp_paypal_payer_email';
    public const FEES_META_KEY = '_ppcp_paypal_fees';
    public const REFUND_FEES_META_KEY = '_ppcp_paypal_refund_fees';
    public const REFUNDS_META_KEY = '_ppcp_refunds';
    public const THREE_D_AUTH_RESULT_META_KEY = '_ppcp_paypal_3DS_auth_result';
    public const FRAUD_RESULT_META_KEY = '_ppcp_paypal_fraud_result';
    // Used by the Contact Module integration.
    public const CONTACT_EMAIL_META_KEY = '_ppcp_paypal_contact_email';
    public const CONTACT_PHONE_META_KEY = '_ppcp_paypal_contact_phone';
    // Used by the Contact Module integration to store the original details.
    public const ORIGINAL_EMAIL_META_KEY = '_ppcp_paypal_billing_email';
    public const ORIGINAL_PHONE_META_KEY = '_ppcp_paypal_billing_phone';
    public const CROSS_BROWSER_APPSWITCH_META_KEY = '_ppcp_cross_browser_appswitch';
    /**
     * List of payment sources for which we are expected to store the payer email in the WC Order metadata.
     */
    const PAYMENT_SOURCES_WITH_PAYER_EMAIL = array('paypal', 'paylater', 'venmo');
    protected FundingSourceRenderer $funding_source_renderer;
    protected OrderProcessor $order_processor;
    protected SettingsProvider $settings_provider;
    protected SessionHandler $session_handler;
    private RefundProcessor $refund_processor;
    protected \WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider $transaction_url_provider;
    protected SubscriptionHelper $subscription_helper;
    protected PaymentTokenRepository $payment_token_repository;
    private bool $onboarded;
    protected Environment $environment;
    private LoggerInterface $logger;
    protected string $api_shop_country;
    /**
     * The function return the PayPal checkout URL for the given order ID.
     *
     * @var callable(string):string
     */
    private $paypal_checkout_url_factory;
    private PaymentTokensEndpoint $payment_tokens_endpoint;
    private bool $vault_v3_enabled;
    private WooCommercePaymentTokens $wc_payment_tokens;
    private bool $admin_settings_enabled;
    private CapturePayPalPayment $capture_paypal_payment;
    private OrderEndpoint $order_endpoint;
    private string $prefix;
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
    public $supports;
    /**
     * Set if the place order button should be renamed on selection.
     *
     * @var string|null
     * @phpstan-ignore property.phpDocType
     */
    public $order_button_text;
    /**
     * @param FundingSourceRenderer    $funding_source_renderer The funding source renderer.
     * @param OrderProcessor           $order_processor The Order Processor.
     * @param SettingsProvider         $config The settings.
     * @param SessionHandler           $session_handler The Session Handler.
     * @param RefundProcessor          $refund_processor The Refund Processor.
     * @param bool                     $is_connected Whether onboarding was completed.
     * @param TransactionUrlProvider   $transaction_url_provider Service providing transaction view URL based on order.
     * @param SubscriptionHelper       $subscription_helper The subscription helper.
     * @param Environment              $environment The environment.
     * @param PaymentTokenRepository   $payment_token_repository The payment token repository.
     * @param LoggerInterface          $logger The logger.
     * @param string                   $api_shop_country The api shop country.
     * @param callable(string):string  $paypal_checkout_url_factory The function return the PayPal checkout URL for the given order ID.
     * @param string                   $place_order_button_text The text for the standard "Place order" button.
     * @param PaymentTokensEndpoint    $payment_tokens_endpoint Payment tokens endpoint.
     * @param bool                     $vault_v3_enabled Whether Vault v3 module is enabled.
     * @param WooCommercePaymentTokens $wc_payment_tokens WooCommerce payment tokens.
     * @param AssetGetter              $asset_getter
     * @param bool                     $admin_settings_enabled Whether settings module is enabled.
     * @param CapturePayPalPayment     $capture_paypal_payment The PayPal vault payment capture endpoint.
     * @param OrderEndpoint            $order_endpoint The order endpoint.
     * @param string                   $prefix The invoice prefix.
     */
    public function __construct(FundingSourceRenderer $funding_source_renderer, OrderProcessor $order_processor, SettingsProvider $config, SessionHandler $session_handler, RefundProcessor $refund_processor, bool $is_connected, \WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider $transaction_url_provider, SubscriptionHelper $subscription_helper, Environment $environment, PaymentTokenRepository $payment_token_repository, LoggerInterface $logger, string $api_shop_country, callable $paypal_checkout_url_factory, string $place_order_button_text, PaymentTokensEndpoint $payment_tokens_endpoint, bool $vault_v3_enabled, WooCommercePaymentTokens $wc_payment_tokens, AssetGetter $asset_getter, bool $admin_settings_enabled, CapturePayPalPayment $capture_paypal_payment, OrderEndpoint $order_endpoint, string $prefix)
    {
        $this->id = self::ID;
        $this->funding_source_renderer = $funding_source_renderer;
        $this->order_processor = $order_processor;
        $this->settings_provider = $config;
        $this->session_handler = $session_handler;
        $this->refund_processor = $refund_processor;
        $this->transaction_url_provider = $transaction_url_provider;
        $this->subscription_helper = $subscription_helper;
        $this->environment = $environment;
        $this->onboarded = $is_connected;
        $this->payment_token_repository = $payment_token_repository;
        $this->logger = $logger;
        $this->api_shop_country = $api_shop_country;
        $this->paypal_checkout_url_factory = $paypal_checkout_url_factory;
        $this->order_button_text = $place_order_button_text;
        $this->payment_tokens_endpoint = $payment_tokens_endpoint;
        $this->vault_v3_enabled = $vault_v3_enabled;
        $this->wc_payment_tokens = $wc_payment_tokens;
        $this->icon = apply_filters('woocommerce_paypal_payments_paypal_gateway_icon', $asset_getter->get_static_asset_url('images/paypal.svg'));
        $this->admin_settings_enabled = $admin_settings_enabled;
        $this->capture_paypal_payment = $capture_paypal_payment;
        $this->order_endpoint = $order_endpoint;
        $this->prefix = $prefix;
        $default_support = array('products', 'refunds', 'tokenization', 'add_payment_method');
        $this->supports = array_merge($default_support, apply_filters('woocommerce_paypal_payments_paypal_gateway_supports', array()));
        $this->method_title = $this->define_method_title();
        $this->method_description = $this->define_method_description();
        $this->title = apply_filters('woocommerce_paypal_payments_gateway_title', $this->settings_provider->paypal_gateway_title(), $this);
        $this->description = apply_filters('woocommerce_paypal_payments_gateway_description', $this->settings_provider->paypal_gateway_description(), $this);
        $funding_source = $this->session_handler->funding_source();
        if ($funding_source) {
            $order = $this->session_handler->order();
            if ($order && ($order->status()->is(OrderStatus::APPROVED) || $order->status()->is(OrderStatus::COMPLETED))) {
                $this->title = $this->funding_source_renderer->render_name($funding_source);
                $this->description = $this->funding_source_renderer->render_description($funding_source);
                $this->order_button_text = null;
            }
        }
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    /**
     * Return the gateway's title.
     *
     * @return string
     */
    public function get_title(): string
    {
        if (is_admin()) {
            // $theorder and other things for retrieving the order or post info are not available
            // in the constructor, so must do it here.
            global $theorder;
            if ($theorder instanceof WC_Order) {
                if ($theorder->get_payment_method() === self::ID) {
                    $payment_method_title = $theorder->get_payment_method_title();
                    if ($payment_method_title) {
                        $this->title = $payment_method_title;
                    }
                }
            }
        }
        return parent::get_title();
    }
    /**
     * Return the gateway's description.
     *
     * @return string
     */
    public function get_description(): string
    {
        $gateway_settings = get_option($this->get_option_key(), array());
        $description = array_key_exists('description', $gateway_settings) ? $gateway_settings['description'] : $this->description;
        /**
         * Filters the gateway description.
         *
         * @param string $description Gateway description (already sanitized with wp_kses_post).
         * @param PayPalGateway $gateway Gateway instance.
         * @return string Filtered gateway description.
         */
        return apply_filters('woocommerce_paypal_payments_gateway_description', wp_kses_post($description), $this);
    }
    /**
     * Whether the Gateway needs to be setup.
     *
     * @return bool
     */
    public function needs_setup(): bool
    {
        return !$this->onboarded;
    }
    /**
     * Initializes the form fields.
     */
    public function init_form_fields(): void
    {
        $this->form_fields = array('enabled' => array('title' => __('Enable/Disable', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'description' => __('In order to use PayPal or Advanced Card Processing, you need to enable the Gateway.', 'woocommerce-paypal-payments'), 'label' => __('Enable the PayPal gateway and more features for your store.', 'woocommerce-paypal-payments'), 'default' => 'no'), 'ppcp' => array('type' => 'ppcp'));
    }
    /**
     * Defines the method title. If we are on the credit card tab in the settings, we want to change this.
     *
     * @return string
     */
    private function define_method_title(): string
    {
        return 'PayPal';
    }
    /**
     * Defines the method description. If we are on the credit card tab in the settings, we want to change this.
     *
     * @return string
     */
    private function define_method_description(): string
    {
        if (is_admin()) {
            return __('Accept PayPal, Pay Later and alternative payment types.', 'woocommerce-paypal-payments');
        }
        return __('Pay via PayPal.', 'woocommerce-paypal-payments');
    }
    /**
     * Process payment for a WooCommerce order.
     *
     * @param int $order_id The WooCommerce order id.
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        $wc_order = wc_get_order($order_id);
        if (!$wc_order instanceof WC_Order) {
            return $this->handle_payment_failure(null, new GatewayGenericException(new Exception('WC order was not found.')));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $funding_source = wc_clean(wp_unslash($_POST['ppcp-funding-source'] ?? $_POST['funding_source'] ?? ''));
        if ($funding_source) {
            $wc_order->set_payment_method_title($this->funding_source_renderer->render_name($funding_source));
            $wc_order->save();
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $paypal_payment_token_id = wc_clean(wp_unslash($_POST['wc-ppcp-gateway-payment-token'] ?? ''));
        if ($paypal_payment_token_id && 'new' !== $paypal_payment_token_id) {
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id());
            foreach ($tokens as $token) {
                if ($token->get_id() === (int) $paypal_payment_token_id) {
                    $payment_source_name = $token instanceof PaymentTokenVenmo ? 'venmo' : 'paypal';
                    $custom_id = (string) $wc_order->get_id();
                    $invoice_id = $this->prefix . $wc_order->get_order_number();
                    try {
                        $created_order = $this->capture_paypal_payment->create_order($token->get_token(), $custom_id, $invoice_id, $wc_order, $payment_source_name);
                    } catch (RuntimeException $exception) {
                        $this->logger->error($exception->getMessage());
                        return $this->handle_payment_failure($wc_order, $exception);
                    }
                    $order = $this->order_endpoint->order($created_order->id());
                    $this->add_paypal_meta($wc_order, $created_order, $this->environment);
                    $wc_order->add_payment_token($token);
                    if ($order->intent() === 'AUTHORIZE') {
                        $order = $this->order_endpoint->authorize($order);
                        $wc_order->update_meta_data(AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false');
                        if ($this->subscription_helper->has_subscription($wc_order->get_id())) {
                            $wc_order->update_meta_data('_ppcp_captured_vault_webhook', 'false');
                        }
                        $wc_order->save();
                    }
                    $transaction_id = $this->get_paypal_order_transaction_id($order);
                    if ($transaction_id) {
                        $this->update_transaction_id($transaction_id, $wc_order);
                    }
                    $this->handle_new_order_status($order, $wc_order);
                    return $this->handle_payment_success($wc_order);
                }
            }
        }
        if ('card' !== $funding_source && $this->is_free_trial_order($wc_order) && !$this->subscription_helper->paypal_subscription_id()) {
            $ppcp_guest_payment_for_free_trial = WC()->session->get('ppcp_guest_payment_for_free_trial') ?? null;
            if ($this->vault_v3_enabled && is_object($ppcp_guest_payment_for_free_trial)) {
                $customer_id = $ppcp_guest_payment_for_free_trial->customer->id ?? '';
                if ($customer_id) {
                    update_user_meta($wc_order->get_customer_id(), '_ppcp_target_customer_id', $customer_id);
                }
                if (isset($ppcp_guest_payment_for_free_trial->payment_source->paypal)) {
                    $email = '';
                    if (isset($ppcp_guest_payment_for_free_trial->payment_source->paypal->email_address)) {
                        $email = $ppcp_guest_payment_for_free_trial->payment_source->paypal->email_address;
                    }
                    $this->wc_payment_tokens->create_payment_token_paypal($wc_order->get_customer_id(), $ppcp_guest_payment_for_free_trial->id, $email);
                }
                WC()->session->set('ppcp_guest_payment_for_free_trial', null);
                $wc_order->payment_complete();
                return $this->handle_payment_success($wc_order);
            }
            $customer_id = get_user_meta($wc_order->get_customer_id(), '_ppcp_target_customer_id', \true);
            if ($customer_id) {
                $customer_tokens = $this->payment_tokens_endpoint->payment_tokens_for_customer($customer_id);
                foreach ($customer_tokens as $token) {
                    $payment_source_name = $token['payment_source']->name() ?? '';
                    if ($payment_source_name === 'paypal' || $payment_source_name === 'venmo') {
                        $wc_order->payment_complete();
                        return $this->handle_payment_success($wc_order);
                    }
                }
            }
            $user_id = (int) $wc_order->get_customer_id();
            $tokens = $this->payment_token_repository->all_for_user_id($user_id);
            if (!array_filter($tokens, function (PaymentToken $token): bool {
                return isset($token->source()->paypal);
            })) {
                return $this->handle_payment_failure($wc_order, new Exception('No saved PayPal account.'));
            }
            $wc_order->payment_complete();
            return $this->handle_payment_success($wc_order);
        }
        /**
         * If the WC_Order is paid through the approved webhook.
         */
        //phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['ppcp-resume-order']) && $wc_order->has_status('processing')) {
            return $this->handle_payment_success($wc_order);
        }
        //phpcs:enable WordPress.Security.NonceVerification.Recommended
        try {
            try {
                /**
                 * This filter controls if the method 'process()' from OrderProcessor will be called.
                 * So you can implement your own for example on subscriptions
                 *
                 * - true bool controls execution of 'OrderProcessor::process()'
                 * - $this \WC_Payment_Gateway
                 * - $wc_order \WC_Order
                 */
                $process = apply_filters('woocommerce_paypal_payments_before_order_process', \true, $this, $wc_order);
                if ($process) {
                    $this->order_processor->process($wc_order);
                }
                do_action('woocommerce_paypal_payments_before_handle_payment_success', $wc_order);
                return $this->handle_payment_success($wc_order);
            } catch (PayPalOrderMissingException $exc) {
                $order = $this->order_processor->create_order($wc_order, is_string($funding_source) && $funding_source ? $funding_source : 'paypal');
                return array('result' => 'success', 'redirect' => ($this->paypal_checkout_url_factory)($order->id()));
            }
        } catch (PayPalApiException $error) {
            $retry_keys_messages = array('INSTRUMENT_DECLINED' => __('Instrument declined.', 'woocommerce-paypal-payments'), 'PAYER_ACTION_REQUIRED' => __('Payer action required, possibly overcharge.', 'woocommerce-paypal-payments'));
            $retry_errors = array_values(array_filter(array_keys($retry_keys_messages), function (string $key) use ($error): bool {
                return $error->has_detail($key);
            }));
            if ($retry_errors) {
                $retry_error_key = $retry_errors[0];
                $wc_order->update_status('failed', $retry_keys_messages[$retry_error_key] . ' ' . ($error->details()[0]->description ?? ''));
                $this->session_handler->increment_insufficient_funding_tries();
                if ($this->session_handler->insufficient_funding_tries() >= 3) {
                    return $this->handle_payment_failure(null, new Exception(__('Please use a different payment method.', 'woocommerce-paypal-payments'), $error->getCode(), $error));
                }
                $session_order = $this->session_handler->order();
                if (!$session_order instanceof Order) {
                    return $this->handle_payment_failure(null, new Exception(__('Payment session expired. Please try again.', 'woocommerce-paypal-payments')));
                }
                return array('result' => 'success', 'redirect' => ($this->paypal_checkout_url_factory)($session_order->id()));
            }
            return $this->handle_payment_failure($wc_order, new Exception(\WooCommerce\PayPalCommerce\WcGateway\Gateway\Messages::generic_payment_error_message() . ' ' . $error->getMessage(), $error->getCode(), $error));
        } catch (Exception $error) {
            return $this->handle_payment_failure($wc_order, $error);
        }
    }
    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     *
     * @param  int    $order_id Order ID.
     * @param  float  $amount Refund amount.
     * @param  string $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
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
     * Override the parent admin_options method.
     */
    public function admin_options(): void
    {
        if (!$this->admin_settings_enabled) {
            parent::admin_options();
        }
        do_action('woocommerce_paypal_payments_gateway_admin_options_wrapper', $this);
    }
}
