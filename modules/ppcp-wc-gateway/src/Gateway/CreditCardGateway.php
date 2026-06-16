<?php

/**
 * The Credit card gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
/**
 * Class CreditCardGateway
 */
class CreditCardGateway extends \WC_Payment_Gateway_CC
{
    use \WooCommerce\PayPalCommerce\WcGateway\Gateway\ProcessPaymentTrait;
    use TransactionIdHandlingTrait;
    use PaymentsStatusHandlingTrait;
    use FreeTrialHandlerTrait;
    use OrderMetaTrait;
    const ID = 'ppcp-credit-card-gateway';
    /**
     * Order meta key holding the one-time, per-attempt random nonce for a
     * vaulted-card payment that is awaiting the buyer to complete a 3D Secure
     * challenge. The same value is embedded in the PayPal return URL and must
     * match on return before the capture is resumed; it is cleared once used.
     *
     * @var string
     */
    const THREE_DS_RESUME_META = '_ppcp_card_3ds_resume';
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
     * @param OrderProcessor            $order_processor             The Order processor.
     * @param ContainerInterface        $config                      The settings.
     * @param CardPaymentsConfiguration $dcc_configuration           The DCC Gateway Configuration.
     * @param array                     $card_icons                  The card icons.
     * @param SessionHandler            $session_handler             The Session Handler.
     * @param RefundProcessor           $refund_processor            The refund processor.
     * @param TransactionUrlProvider    $transaction_url_provider    Service able to provide view transaction url base.
     * @param SubscriptionHelper        $subscription_helper         The subscription helper.
     * @param PaymentsEndpoint          $payments_endpoint           The payments endpoint.
     * @param Environment               $environment                 The environment.
     * @param OrderEndpoint             $order_endpoint              The order endpoint.
     * @param CaptureCardPayment        $capture_card_payment        Capture card payment.
     * @param WooCommercePaymentTokens  $wc_payment_tokens           WooCommerce payment tokens factory.
     * @param LoggerInterface           $logger                      The logger.
     */
    public function __construct(OrderProcessor $order_processor, ContainerInterface $config, CardPaymentsConfiguration $dcc_configuration, array $card_icons, SessionHandler $session_handler, RefundProcessor $refund_processor, \WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider $transaction_url_provider, SubscriptionHelper $subscription_helper, PaymentsEndpoint $payments_endpoint, Environment $environment, OrderEndpoint $order_endpoint, CaptureCardPayment $capture_card_payment, WooCommercePaymentTokens $wc_payment_tokens, LoggerInterface $logger)
    {
        $this->id = self::ID;
        $this->order_processor = $order_processor;
        $this->config = $config;
        $this->dcc_configuration = $dcc_configuration;
        $this->session_handler = $session_handler;
        $this->refund_processor = $refund_processor;
        $this->transaction_url_provider = $transaction_url_provider;
        $this->subscription_helper = $subscription_helper;
        $this->payments_endpoint = $payments_endpoint;
        $this->environment = $environment;
        $this->order_endpoint = $order_endpoint;
        $this->capture_card_payment = $capture_card_payment;
        $this->wc_payment_tokens = $wc_payment_tokens;
        $this->logger = $logger;
        $default_support = array('products', 'refunds');
        $this->supports = array_merge($default_support, apply_filters('woocommerce_paypal_payments_credit_card_gateway_supports', array()));
        $this->method_title = __('Debit & Credit Cards', 'woocommerce-paypal-payments');
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
        if (!$wc_order instanceof WC_Order) {
            WC()->session->set('ppcp_card_payment_token_for_free_trial', null);
            return $this->handle_payment_failure(null, new GatewayGenericException(new Exception('WC order was not found.')));
        }
        $guest_card_payment_for_free_trial = WC()->session->get('ppcp_guest_payment_for_free_trial') ?? null;
        WC()->session->get('ppcp_guest_payment_for_free_trial', null);
        if (is_object($guest_card_payment_for_free_trial)) {
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
        if ($this->is_free_trial_order($wc_order) && $card_payment_token_id && 'new' !== $card_payment_token_id) {
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
         * Resume a vaulted-card payment after the buyer completed a 3D Secure
         * challenge. The one-time nonce in the return URL must match the value
         * stored on the order; it is cleared on use, so the resume is single-use.
         */
        // wp_unslash() can return an array, so the value is sanitized on the next line behind an is_string() guard.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $provided_resume_nonce = wp_unslash($_GET['ppcp_resume_nonce'] ?? '');
        $provided_resume_nonce = is_string($provided_resume_nonce) ? sanitize_text_field($provided_resume_nonce) : '';
        $stored_resume_nonce = (string) $wc_order->get_meta(self::THREE_DS_RESUME_META);
        if ($stored_resume_nonce && $provided_resume_nonce && hash_equals($stored_resume_nonce, $provided_resume_nonce)) {
            $wc_order->delete_meta_data(self::THREE_DS_RESUME_META);
            $wc_order->save();
            try {
                $order = $this->order_endpoint->order((string) $wc_order->get_meta(\WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway::ORDER_ID_META_KEY));
                return $this->finalize_vaulted_card_order($wc_order, $order);
            } catch (RuntimeException $exception) {
                $this->logger->error($exception->getMessage());
                return $this->handle_payment_failure($wc_order, $exception);
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
                    try {
                        $resume_nonce = wp_generate_password(20, \false);
                        $created_order = $this->capture_card_payment->create_order($token->get_token(), $wc_order, $resume_nonce);
                        $this->add_paypal_meta($wc_order, $created_order, $this->environment);
                        $wc_order->add_payment_token($token);
                        /**
                         * Step-up: when PayPal returns a payer-action link the buyer must
                         * complete a 3D Secure challenge before this vaulted card can be
                         * charged. PayPal returns the order in CREATED (or
                         * PAYER_ACTION_REQUIRED) status with that link; a frictionless
                         * charge has no such link. Store this attempt's one-time nonce and
                         * redirect to the payer-action URL; the capture resumes when the
                         * buyer returns with the matching nonce. A later attempt overwrites
                         * the nonce, so only the most recent challenge can be confirmed.
                         */
                        $payer_action = $this->payer_action_url($created_order);
                        if ($payer_action) {
                            $wc_order->update_meta_data(self::THREE_DS_RESUME_META, $resume_nonce);
                            $wc_order->save();
                            return array('result' => 'success', 'redirect' => $payer_action);
                        }
                        $order = $this->order_endpoint->order($created_order->id());
                        return $this->finalize_vaulted_card_order($wc_order, $order);
                    } catch (RuntimeException $exception) {
                        $this->logger->error($exception->getMessage());
                        return $this->handle_payment_failure($wc_order, $exception);
                    }
                }
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
     * Authorizes or captures a vaulted-card PayPal order, records the transaction
     * id, transitions the WC order and returns the WC payment result. Shared by
     * the initial saved-card charge and the resume step after a 3D Secure
     * challenge; the status checks keep it idempotent whether or not the order
     * was already captured during the return.
     *
     * @param WC_Order $wc_order The WC order.
     * @param Order    $order    The PayPal order to finalize.
     * @return array The WC payment result.
     * @throws RuntimeException When an API request fails.
     */
    private function finalize_vaulted_card_order(WC_Order $wc_order, Order $order): array
    {
        if ($order->intent() === 'AUTHORIZE') {
            $order = $this->order_endpoint->authorize($order);
            $wc_order->update_meta_data(AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false');
            if ($this->subscription_helper->has_subscription($wc_order->get_id())) {
                $wc_order->update_meta_data('_ppcp_captured_vault_webhook', 'false');
            }
        } elseif ($order->status()->is(OrderStatus::APPROVED) || $order->status()->is(OrderStatus::CREATED)) {
            // A vaulted-card order is created in CREATED status and must be
            // captured explicitly; without this it never leaves "pending payment".
            $order = $this->order_endpoint->capture($order);
        }
        $transaction_id = $this->get_paypal_order_transaction_id($order);
        if ($transaction_id) {
            $this->update_transaction_id($transaction_id, $wc_order);
        }
        $this->handle_new_order_status($order, $wc_order);
        /**
         * Safety net: if nothing transitioned the order into a handled state
         * (e.g. PayPal returned PAYER_ACTION_REQUIRED and produced no capture or
         * authorization), fail cleanly instead of leaving the order silently
         * stuck in "pending payment".
         */
        if (!$wc_order->has_status(array('processing', 'completed', 'on-hold'))) {
            $this->logger->error(sprintf('Vaulted card payment for WC order %1$d did not reach a handled state (PayPal order %2$s, status %3$s); failing the payment.', $wc_order->get_id(), $order->id(), $order->status()->name()));
            return $this->handle_payment_failure($wc_order, new RuntimeException(__('This saved card could not be charged. Please try another payment method.', 'woocommerce-paypal-payments')));
        }
        return $this->handle_payment_success($wc_order);
    }
    /**
     * Returns the payer-action URL from a PayPal order's HATEOAS links, or an
     * empty string when none is present.
     *
     * @param Order $order The PayPal order.
     * @return string
     */
    private function payer_action_url(Order $order): string
    {
        $links = $order->links();
        if (!is_array($links)) {
            return '';
        }
        foreach ($links as $link) {
            if (is_object($link) && isset($link->rel, $link->href) && 'payer-action' === $link->rel) {
                return (string) $link->href;
            }
        }
        return '';
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
        if (!$order instanceof \WC_Order) {
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
            assert($this->config instanceof Settings);
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
