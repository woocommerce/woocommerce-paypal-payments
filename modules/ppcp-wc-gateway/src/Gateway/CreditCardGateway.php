<?php

/**
 * The Credit card gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use DomainException;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
/**
 * Class CreditCardGateway
 */
class CreditCardGateway extends \WC_Payment_Gateway_CC
{
    use \WooCommerce\PayPalCommerce\WcGateway\Gateway\ProcessPaymentTrait;
    use \WooCommerce\PayPalCommerce\WcGateway\Gateway\GatewaySettingsRendererTrait;
    use TransactionIdHandlingTrait;
    use PaymentsStatusHandlingTrait;
    use FreeTrialHandlerTrait;
    const ID = 'ppcp-credit-card-gateway';
    /**
     * The Settings Renderer.
     *
     * @var SettingsRenderer
     */
    protected $settings_renderer;
    /**
     * The processor for orders.
     *
     * @var OrderProcessor
     */
    protected $order_processor;
    /**
     * The card icons.
     *
     * @var array
     */
    protected $card_icons;
    /**
     * The settings.
     *
     * @var ContainerInterface
     */
    protected $config;
    /**
     * The DCC Gateway Configuration.
     *
     * @var CardPaymentsConfiguration
     */
    protected CardPaymentsConfiguration $dcc_configuration;
    /**
     * The vaulted credit card handler.
     *
     * @var VaultedCreditCardHandler
     */
    protected $vaulted_credit_card_handler;
    /**
     * The URL to the module.
     *
     * @var string
     */
    private $module_url;
    /**
     * The Session Handler.
     *
     * @var SessionHandler
     */
    protected $session_handler;
    /**
     * The refund processor.
     *
     * @var RefundProcessor
     */
    private $refund_processor;
    /**
     * Service to get transaction url for an order.
     *
     * @var TransactionUrlProvider
     */
    protected $transaction_url_provider;
    /**
     * The payment token repository.
     *
     * @var PaymentTokenRepository
     */
    private $payment_token_repository;
    /**
     * The subscription helper.
     *
     * @var SubscriptionHelper
     */
    protected $subscription_helper;
    /**
     * The payments endpoint
     *
     * @var PaymentsEndpoint
     */
    protected $payments_endpoint;
    /**
     * The environment.
     *
     * @var Environment
     */
    private $environment;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * Capture card payment.
     *
     * @var CaptureCardPayment
     */
    private $capture_card_payment;
    /**
     * The prefix.
     *
     * @var string
     */
    private $prefix;
    /**
     * Payment tokens endpoint.
     *
     * @var PaymentTokensEndpoint
     */
    private $payment_tokens_endpoint;
    /**
     * WooCommerce payment tokens factory.
     *
     * @var WooCommercePaymentTokens
     */
    private $wc_payment_tokens;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * ID of the class extending the settings API. Used in option names.
     *
     * @var string
     */
    public $id;
    /**
     * Supported features such as 'default_credit_card_form', 'refunds'.
     *
     * @var array
     */
    public $supports = array('products');
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
     * Yes or no based on whether the method is enabled.
     *
     * @var string
     */
    public $enabled = 'yes';
    /**
     * CreditCardGateway constructor.
     *
     * @param SettingsRenderer          $settings_renderer           The Settings Renderer.
     * @param OrderProcessor            $order_processor             The Order processor.
     * @param ContainerInterface        $config                      The settings.
     * @param CardPaymentsConfiguration $dcc_configuration           The DCC Gateway Configuration.
     * @param array                     $card_icons                  The card icons.
     * @param string                    $module_url                  The URL to the module.
     * @param SessionHandler            $session_handler             The Session Handler.
     * @param RefundProcessor           $refund_processor            The refund processor.
     * @param TransactionUrlProvider    $transaction_url_provider    Service able to provide view transaction url base.
     * @param SubscriptionHelper        $subscription_helper         The subscription helper.
     * @param PaymentsEndpoint          $payments_endpoint           The payments endpoint.
     * @param VaultedCreditCardHandler  $vaulted_credit_card_handler The vaulted credit card handler.
     * @param Environment               $environment                 The environment.
     * @param OrderEndpoint             $order_endpoint              The order endpoint.
     * @param CaptureCardPayment        $capture_card_payment        Capture card payment.
     * @param string                    $prefix                      The prefix.
     * @param PaymentTokensEndpoint     $payment_tokens_endpoint     Payment tokens endpoint.
     * @param WooCommercePaymentTokens  $wc_payment_tokens           WooCommerce payment tokens factory.
     * @param LoggerInterface           $logger                      The logger.
     */
    public function __construct(SettingsRenderer $settings_renderer, OrderProcessor $order_processor, ContainerInterface $config, CardPaymentsConfiguration $dcc_configuration, array $card_icons, string $module_url, SessionHandler $session_handler, RefundProcessor $refund_processor, \WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider $transaction_url_provider, SubscriptionHelper $subscription_helper, PaymentsEndpoint $payments_endpoint, VaultedCreditCardHandler $vaulted_credit_card_handler, Environment $environment, OrderEndpoint $order_endpoint, CaptureCardPayment $capture_card_payment, string $prefix, PaymentTokensEndpoint $payment_tokens_endpoint, WooCommercePaymentTokens $wc_payment_tokens, LoggerInterface $logger)
    {
        $this->id = self::ID;
        $this->settings_renderer = $settings_renderer;
        $this->order_processor = $order_processor;
        $this->config = $config;
        $this->dcc_configuration = $dcc_configuration;
        $this->module_url = $module_url;
        $this->session_handler = $session_handler;
        $this->refund_processor = $refund_processor;
        $this->transaction_url_provider = $transaction_url_provider;
        $this->subscription_helper = $subscription_helper;
        $this->payments_endpoint = $payments_endpoint;
        $this->vaulted_credit_card_handler = $vaulted_credit_card_handler;
        $this->environment = $environment;
        $this->order_endpoint = $order_endpoint;
        $this->capture_card_payment = $capture_card_payment;
        $this->prefix = $prefix;
        $this->payment_tokens_endpoint = $payment_tokens_endpoint;
        $this->wc_payment_tokens = $wc_payment_tokens;
        $this->logger = $logger;
        $default_support = array('products', 'refunds');
        $this->supports = array_merge($default_support, apply_filters('woocommerce_paypal_payments_credit_card_gateway_supports', array()));
        $this->method_title = __('Advanced Card Processing', 'woocommerce-paypal-payments');
        $this->method_description = __('Accept debit and credit cards, and local payment methods with PayPal’s latest solution.', 'woocommerce-paypal-payments');
        $this->title = apply_filters('woocommerce_paypal_payments_credit_card_gateway_title', $this->dcc_configuration->gateway_title(), $this);
        $this->description = apply_filters('woocommerce_paypal_payments_credit_card_gateway_description', $this->dcc_configuration->gateway_description(), $this);
        $this->card_icons = $card_icons;
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    /**
     * Initialize the form fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = apply_filters('woocommerce_paypal_payments_credit_card_gateway_form_fields', array('ppcp' => array('type' => 'ppcp')));
    }
    /**
     * Render the credit card fields.
     */
    public function form()
    {
        add_action('gettext', array($this, 'replace_credit_card_cvv_label'), 10, 3);
        add_action('gettext', array($this, 'replace_credit_card_cvv_placeholder'), 10, 3);
        parent::form();
        remove_action('gettext', 'replace_credit_card_cvv_label');
        remove_action('gettext', 'replace_credit_card_cvv_placeholder');
    }
    /**
     * Replace WooCommerce credit card field label.
     *
     * @param string $translation Translated text.
     * @param string $text Original text to translate.
     * @param string $domain Text domain.
     *
     * @return string Translated field.
     */
    public function replace_credit_card_cvv_label(string $translation, string $text, string $domain): string
    {
        if ('woocommerce' !== $domain || 'Card code' !== $text) {
            return $translation;
        }
        return __('CVV', 'woocommerce-paypal-payments');
    }
    /**
     * Replace WooCommerce credit card CVV field placeholder.
     *
     * @param string $translation Translated text.
     * @param string $text Original text to translate.
     * @param string $domain Text domain.
     *
     * @return string Translated field.
     */
    public function replace_credit_card_cvv_placeholder(string $translation, string $text, string $domain): string
    {
        if ('woocommerce' !== $domain || 'CVC' !== $text || !apply_filters('woocommerce_paypal_payments_card_fields_translate_card_cvv', \true)) {
            return $translation;
        }
        return __('CVV', 'woocommerce-paypal-payments');
    }
    /**
     * Returns the icons of the gateway.
     *
     * @return string
     */
    public function get_icon()
    {
        $icon = parent::get_icon();
        $icons = $this->card_icons;
        if (!$icons) {
            return $icon;
        }
        $images = array();
        foreach ($icons as $card) {
            $images[] = '<img
				class="ppcp-card-icon"
				title="' . esc_attr($card['title']) . '"
				src="' . esc_url($card['url']) . '"
			> ';
        }
        return implode('', $images);
    }
    /**
     * Whether the gateway is available or not.
     *
     * @return bool
     */
    public function is_available(): bool
    {
        return $this->is_enabled();
    }
    /**
     * Process payment for a WooCommerce order.
     *
     * @param int $order_id The WooCommerce order id.
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        $wc_order = wc_get_order($order_id);
        if (!is_a($wc_order, WC_Order::class)) {
            WC()->session->set('ppcp_card_payment_token_for_free_trial', null);
            return $this->handle_payment_failure(null, new GatewayGenericException(new Exception('WC order was not found.')));
        }
        $guest_card_payment_for_free_trial = WC()->session->get('ppcp_guest_payment_for_free_trial') ?? null;
        WC()->session->get('ppcp_guest_payment_for_free_trial', null);
        if ($guest_card_payment_for_free_trial) {
            $customer_id = $guest_card_payment_for_free_trial->customer->id ?? '';
            if ($customer_id) {
                update_user_meta($wc_order->get_customer_id(), '_ppcp_target_customer_id', $customer_id);
            }
            if (isset($guest_card_payment_for_free_trial->payment_source->card)) {
                $this->wc_payment_tokens->create_payment_token_card($wc_order->get_customer_id(), $guest_card_payment_for_free_trial);
                $wc_order->payment_complete();
                return $this->handle_payment_success($wc_order);
            }
        }
        $card_payment_token_for_free_trial = WC()->session->get('ppcp_card_payment_token_for_free_trial') ?? null;
        WC()->session->set('ppcp_card_payment_token_for_free_trial', null);
        if ($card_payment_token_for_free_trial) {
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id());
            foreach ($tokens as $token) {
                if ($token->get_id() === (int) $card_payment_token_for_free_trial) {
                    $wc_order->payment_complete();
                    $wc_order->add_payment_token($token);
                    return $this->handle_payment_success($wc_order);
                }
            }
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $card_payment_token_id = wc_clean(wp_unslash($_POST['wc-ppcp-credit-card-gateway-payment-token'] ?? ''));
        if ($this->is_free_trial_order($wc_order) && $card_payment_token_id) {
            $customer_tokens = $this->wc_payment_tokens->customer_tokens(get_current_user_id());
            foreach ($customer_tokens as $token) {
                if ($token['payment_source']->name() === 'card') {
                    $wc_order->payment_complete();
                    return $this->handle_payment_success($wc_order);
                }
            }
        }
        if ($this->is_customer_changing_subscription_payment($this->subscription_helper, $wc_order)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $wc_payment_token_id = wc_clean(wp_unslash($_POST['wc-ppcp-credit-card-gateway-payment-token'] ?? ''));
            if (!$wc_payment_token_id) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $wc_payment_token_id = wc_clean(wp_unslash($_POST['saved_credit_card'] ?? ''));
            }
            if ($wc_payment_token_id) {
                return $this->add_payment_token_to_order($wc_order, (int) $wc_payment_token_id, $this->get_return_url($wc_order), $this->session_handler);
            }
        }
        /**
         * Vault v3 (save payment methods).
         * If customer has chosen a saved credit card payment from checkout page.
         */
        if ($card_payment_token_id) {
            $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id());
            foreach ($tokens as $token) {
                if ($token->get_id() === (int) $card_payment_token_id) {
                    $custom_id = (string) $wc_order->get_id();
                    $invoice_id = $this->prefix . $wc_order->get_order_number();
                    try {
                        $create_order = $this->capture_card_payment->create_order($token->get_token(), $custom_id, $invoice_id, $wc_order);
                    } catch (RuntimeException $exception) {
                        $this->logger->error($exception->getMessage());
                    }
                    $order = $this->order_endpoint->order($create_order->id);
                    $wc_order->update_meta_data(\WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway::INTENT_META_KEY, $order->intent());
                    $wc_order->add_payment_token($token);
                    if ($order->intent() === 'AUTHORIZE') {
                        $order = $this->order_endpoint->authorize($order);
                        $wc_order->update_meta_data(AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false');
                        if ($this->subscription_helper->has_subscription($wc_order->get_id())) {
                            $wc_order->update_meta_data('_ppcp_captured_vault_webhook', 'false');
                        }
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
        /**
         * If customer has chosen a saved credit card payment from checkout page.
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $saved_credit_card = wc_clean(wp_unslash($_POST['saved_credit_card'] ?? ''));
        if ($saved_credit_card && is_checkout()) {
            try {
                $wc_order = $this->vaulted_credit_card_handler->handle_payment($saved_credit_card, $wc_order);
                return $this->handle_payment_success($wc_order);
            } catch (RuntimeException $error) {
                return $this->handle_payment_failure($wc_order, $error);
            }
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
        } catch (PayPalApiException $error) {
            return $this->handle_payment_failure($wc_order, new Exception(\WooCommerce\PayPalCommerce\WcGateway\Gateway\Messages::generic_payment_error_message() . ' ' . $error->getMessage(), $error->getCode(), $error));
        } catch (Exception $error) {
            return $this->handle_payment_failure($wc_order, $error);
        }
    }
    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
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
     * Set the class property then call parent function.
     *
     * @param \WC_Order $order WC Order to get transaction url for.
     *
     * @inheritDoc
     */
    public function get_transaction_url($order): string
    {
        $this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base($order);
        return parent::get_transaction_url($order);
    }
    /**
     * Initialize settings for WC.
     *
     * @return void
     */
    public function init_settings()
    {
        parent::init_settings();
        if (!apply_filters('woocommerce_paypal_payments_credit_card_gateway_should_update_enabled', \true)) {
            return;
        }
        // looks like in some cases WC uses this field instead of get_option.
        $this->enabled = $this->is_enabled() ? 'yes' : '';
    }
    /**
     * Get the option value for WC.
     *
     * @param string $key The option key.
     * @param mixed  $empty_value Value when empty.
     * @return mixed
     */
    public function get_option($key, $empty_value = null)
    {
        if (!apply_filters('woocommerce_paypal_payments_credit_card_gateway_should_update_enabled', \true)) {
            return parent::get_option($key, $empty_value);
        }
        if ('enabled' === $key) {
            return $this->is_enabled();
        }
        return parent::get_option($key, $empty_value);
    }
    /**
     * Handle update of WC settings.
     *
     * @param string $key The option key.
     * @param string $value The option value.
     * @return bool was anything saved?
     */
    public function update_option($key, $value = '')
    {
        $ret = parent::update_option($key, $value);
        if (!apply_filters('woocommerce_paypal_payments_credit_card_gateway_should_update_enabled', \true)) {
            return $ret;
        }
        if ('enabled' === $key) {
            $this->config->set('dcc_enabled', 'yes' === $value);
            $this->config->persist();
            $this->dcc_configuration->refresh();
            return \true;
        }
        return $ret;
    }
    /**
     * Returns if the gateway is enabled.
     *
     * @return bool
     */
    private function is_enabled(): bool
    {
        return $this->dcc_configuration->is_enabled();
    }
    /**
     * Returns the settings renderer.
     *
     * @return SettingsRenderer
     */
    protected function settings_renderer(): SettingsRenderer
    {
        return $this->settings_renderer;
    }
    /**
     * Check whether customer is changing subscription payment.
     *
     * @param SubscriptionHelper $subscription_helper Subscription helper.
     * @param WC_Order           $wc_order WC order.
     * @return bool
     */
    private function is_customer_changing_subscription_payment(SubscriptionHelper $subscription_helper, WC_Order $wc_order): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset($_POST['woocommerce_change_payment']) && $subscription_helper->has_subscription($wc_order->get_id()) && $subscription_helper->is_subscription_change_payment();
    }
    /**
     * Adds the given WC payment token into the given WC Order.
     *
     * @param WC_Order       $wc_order WC order.
     * @param int            $wc_payment_token_id WC payment token ID.
     * @param string         $return_url Return url.
     * @param SessionHandler $session_handler Session handler.
     * @return array{result: string, redirect: string, errorMessage?: string}
     */
    private function add_payment_token_to_order(WC_Order $wc_order, int $wc_payment_token_id, string $return_url, SessionHandler $session_handler): array
    {
        $payment_token = WC_Payment_Tokens::get($wc_payment_token_id);
        if ($payment_token) {
            $wc_order->add_payment_token($payment_token);
            $wc_order->save();
            $session_handler->destroy_session_data();
            return array('result' => 'success', 'redirect' => $return_url);
        }
        wc_add_notice(__('Could not change payment.', 'woocommerce-paypal-payments'), 'error');
        return array('result' => 'failure', 'redirect' => wc_get_checkout_url(), 'errorMessage' => __('Could not change payment.', 'woocommerce-paypal-payments'));
    }
}
