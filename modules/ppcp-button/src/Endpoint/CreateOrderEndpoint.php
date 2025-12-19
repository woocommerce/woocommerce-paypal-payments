<?php

/**
 * The endpoint to create an PayPal order.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use stdClass;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ReturnUrlFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\Button\Validation\CheckoutFormValidator;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcGateway\CardBillingMode;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ContactPreferenceFactory;
/**
 * Class CreateOrderEndpoint
 */
class CreateOrderEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    use FreeTrialHandlerTrait;
    const ENDPOINT = 'ppc-create-order';
    public const RETURN_URL_CART_QUERY_ARG = 'pcp-cart';
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The PurchaseUnit factory.
     *
     * @var PurchaseUnitFactory
     */
    private $purchase_unit_factory;
    /**
     * The shipping_preference factory.
     *
     * @var ShippingPreferenceFactory
     */
    private $shipping_preference_factory;
    private ReturnUrlFactory $return_url_factory;
    /**
     * The contact_preference factors.
     */
    private ContactPreferenceFactory $contact_preference_factory;
    /**
     * The ExperienceContextBuilder.
     */
    private ExperienceContextBuilder $experience_context_builder;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $api_endpoint;
    /**
     * The payer factory.
     *
     * @var PayerFactory
     */
    private $payer_factory;
    /**
     * The session handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * The settings.
     *
     * @var Settings
     */
    private $settings;
    /**
     * The early order handler.
     *
     * @var EarlyOrderHandler
     */
    private $early_order_handler;
    protected CartDataFactory $cart_data_factory;
    protected CartDataTransientStorage $cart_data_transient_storage;
    /**
     * Data from the request.
     *
     * @var array
     */
    private $parsed_request_data;
    /**
     * The purchase unit for order.
     *
     * @var PurchaseUnit|null
     */
    private $purchase_unit;
    /**
     * Whether a new user must be registered during checkout.
     *
     * @var bool
     */
    private $registration_needed;
    /**
     * The value of card_billing_data_mode from the settings.
     *
     * @var string
     */
    protected $card_billing_data_mode;
    /**
     * Whether to execute WC validation of the checkout form.
     *
     * @var bool
     */
    protected $early_validation_enabled;
    /**
     * The contexts that should have the Pay Now button.
     *
     * @var string[]
     */
    private $pay_now_contexts;
    /**
     * If true, the shipping methods are sent to PayPal allowing the customer to select it inside the popup.
     *
     * @var bool
     */
    private $handle_shipping_in_paypal;
    /**
     * Whether the server-side shipping callback is enabled (feature flag).
     */
    private bool $server_side_shipping_callback_enabled;
    /**
     * The sources that do not cause issues about redirecting (on mobile, ...) and sometimes not returning back.
     *
     * @var string[]
     */
    private $funding_sources_without_redirect;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * The form data, or empty if not available.
     *
     * @var array
     */
    private $form = array();
    /**
     * CreateOrderEndpoint constructor.
     *
     * @param RequestData               $request_data The RequestData object.
     * @param PurchaseUnitFactory       $purchase_unit_factory The PurchaseUnit factory.
     * @param ShippingPreferenceFactory $shipping_preference_factory The shipping_preference factory.
     * @param ReturnUrlFactory          $return_url_factory  The return URL factory.
     * @param ContactPreferenceFactory  $contact_preference_factory The contact_preference factory.
     * @param ExperienceContextBuilder  $experience_context_builder The ExperienceContextBuilder.
     * @param OrderEndpoint             $order_endpoint The OrderEndpoint object.
     * @param PayerFactory              $payer_factory The PayerFactory object.
     * @param SessionHandler            $session_handler The SessionHandler object.
     * @param Settings                  $settings The Settings object.
     * @param EarlyOrderHandler         $early_order_handler The EarlyOrderHandler object.
     * @param CartDataFactory           $cart_data_factory
     * @param CartDataTransientStorage  $cart_data_transient_storage
     * @param bool                      $registration_needed  Whether a new user must be registered during checkout.
     * @param string                    $card_billing_data_mode The value of card_billing_data_mode from the settings.
     * @param bool                      $early_validation_enabled Whether to execute WC validation of the checkout form.
     * @param string[]                  $pay_now_contexts The contexts that should have the Pay Now button.
     * @param bool                      $handle_shipping_in_paypal If true, the shipping methods are sent to PayPal allowing the customer to select it inside the popup.
     * @param bool                      $server_side_shipping_callback_enabled Whether the server-side shipping callback is enabled (feature flag).
     * @param string[]                  $funding_sources_without_redirect The sources that do not cause issues about redirecting (on mobile, ...) and sometimes not returning back.
     * @param LoggerInterface           $logger The logger.
     */
    public function __construct(\WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data, PurchaseUnitFactory $purchase_unit_factory, ShippingPreferenceFactory $shipping_preference_factory, ReturnUrlFactory $return_url_factory, ContactPreferenceFactory $contact_preference_factory, ExperienceContextBuilder $experience_context_builder, OrderEndpoint $order_endpoint, PayerFactory $payer_factory, SessionHandler $session_handler, Settings $settings, EarlyOrderHandler $early_order_handler, CartDataFactory $cart_data_factory, CartDataTransientStorage $cart_data_transient_storage, bool $registration_needed, string $card_billing_data_mode, bool $early_validation_enabled, array $pay_now_contexts, bool $handle_shipping_in_paypal, bool $server_side_shipping_callback_enabled, array $funding_sources_without_redirect, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->shipping_preference_factory = $shipping_preference_factory;
        $this->contact_preference_factory = $contact_preference_factory;
        $this->return_url_factory = $return_url_factory;
        $this->experience_context_builder = $experience_context_builder;
        $this->api_endpoint = $order_endpoint;
        $this->payer_factory = $payer_factory;
        $this->session_handler = $session_handler;
        $this->settings = $settings;
        $this->early_order_handler = $early_order_handler;
        $this->cart_data_factory = $cart_data_factory;
        $this->cart_data_transient_storage = $cart_data_transient_storage;
        $this->registration_needed = $registration_needed;
        $this->card_billing_data_mode = $card_billing_data_mode;
        $this->early_validation_enabled = $early_validation_enabled;
        $this->pay_now_contexts = $pay_now_contexts;
        $this->handle_shipping_in_paypal = $handle_shipping_in_paypal;
        $this->server_side_shipping_callback_enabled = $server_side_shipping_callback_enabled;
        $this->funding_sources_without_redirect = $funding_sources_without_redirect;
        $this->logger = $logger;
    }
    /**
     * Returns the nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     *
     * @return bool
     * @throws Exception On Error.
     */
    public function handle_request(): bool
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $this->parsed_request_data = $data;
            $payment_method = $data['payment_method'] ?? '';
            $funding_source = $data['funding_source'] ?? '';
            $wc_order = null;
            do_action('woocommerce_paypal_payments_create_order_request_started', $data);
            if ('pay-now' === $data['context']) {
                $wc_order = wc_get_order((int) $data['order_id']);
                if (!is_a($wc_order, WC_Order::class)) {
                    wp_send_json_error(array('name' => 'order-not-found', 'message' => __('Order not found', 'woocommerce-paypal-payments'), 'code' => 0, 'details' => array()));
                }
                $this->purchase_unit = $this->purchase_unit_factory->from_wc_order($wc_order);
            } else {
                $this->purchase_unit = $this->purchase_unit_factory->from_wc_cart(null, $this->should_handle_shipping_in_paypal($funding_source));
                // Do not allow completion by webhooks when started via non-checkout buttons,
                // it is needed only for some APMs in checkout.
                if (in_array($data['context'], array('product', 'cart', 'cart-block'), \true)) {
                    $this->purchase_unit->set_custom_id('');
                }
                // The cart does not have any info about payment method, so we must handle free trial here.
                if ((in_array($payment_method, array(CreditCardGateway::ID, CardButtonGateway::ID), \true) || PayPalGateway::ID === $payment_method && 'card' === $funding_source) && $this->is_free_trial_cart()) {
                    $this->purchase_unit->set_amount(new Amount(new Money(1.0, $this->purchase_unit->amount()->currency_code()), $this->purchase_unit->amount()->breakdown()));
                }
            }
            $this->set_bn_code($data);
            if (isset($data['form'])) {
                $this->form = $data['form'];
            }
            if ($this->early_validation_enabled && $this->form && 'checkout' === $data['context'] && in_array($payment_method, array(PayPalGateway::ID, CardButtonGateway::ID, CreditCardGateway::ID), \true)) {
                $this->validate_form($this->form);
            }
            if ('pay-now' === $data['context'] && $this->form && get_option('woocommerce_terms_page_id', '') !== '') {
                $this->validate_paynow_form($this->form);
            }
            try {
                $order = $this->create_paypal_order($wc_order, $payment_method, $data);
            } catch (Exception $exception) {
                $this->logger->error('Order creation failed: ' . $exception->getMessage());
                throw $exception;
            }
            if ('checkout' === $data['context']) {
                if ($payment_method === PayPalGateway::ID && !in_array($funding_source, $this->funding_sources_without_redirect, \true)) {
                    $this->session_handler->replace_order($order);
                    $this->session_handler->replace_funding_source($funding_source);
                }
                if (!$this->early_order_handler->should_create_early_order() || $this->registration_needed || isset($data['createaccount']) && '1' === $data['createaccount']) {
                    wp_send_json_success($this->make_response($order));
                }
                $this->early_order_handler->register_for_order($order);
            }
            if ('pay-now' === $data['context'] && is_a($wc_order, WC_Order::class)) {
                $wc_order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $order->id());
                $wc_order->update_meta_data(PayPalGateway::INTENT_META_KEY, $order->intent());
                $payment_source = $order->payment_source();
                $payment_source_name = $payment_source ? $payment_source->name() : null;
                $payer = $order->payer();
                if ($payer && $payment_source_name && in_array($payment_source_name, PayPalGateway::PAYMENT_SOURCES_WITH_PAYER_EMAIL, \true)) {
                    $payer_email = $payer->email_address();
                    if ($payer_email) {
                        $wc_order->update_meta_data(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY, $payer_email);
                    }
                }
                $wc_order->save_meta_data();
                do_action('woocommerce_paypal_payments_woocommerce_order_created', $wc_order, $order);
            }
            wp_send_json_success($this->make_response($order));
            return \true;
        } catch (ValidationException $error) {
            $response = array('message' => $error->getMessage(), 'errors' => $error->errors(), 'refresh' => isset(WC()->session->refresh_totals));
            unset(WC()->session->refresh_totals);
            wp_send_json_error($response);
        } catch (\RuntimeException $error) {
            $this->logger->error('Order creation failed: ' . $error->getMessage());
            wp_send_json_error(array('name' => is_a($error, PayPalApiException::class) ? $error->name() : '', 'message' => $error->getMessage(), 'code' => $error->getCode(), 'details' => is_a($error, PayPalApiException::class) ? $error->details() : array()));
        } catch (Exception $exception) {
            $this->logger->error('Order creation failed: ' . $exception->getMessage());
            wc_add_notice($exception->getMessage(), 'error');
        }
        return \false;
    }
    /**
     * Creates the order in the PayPal, uses data from WC order if provided.
     *
     * @param WC_Order|null $wc_order WC order to get data from.
     * @param string        $payment_method WC payment method.
     * @param array         $data Request data.
     *
     * @return Order Created PayPal order.
     *
     * @throws RuntimeException If create order request fails.
     * @throws PayPalApiException If create order request fails.
     *
     * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
     */
    private function create_paypal_order(?WC_Order $wc_order = null, string $payment_method = '', array $data = array()): Order
    {
        assert($this->purchase_unit instanceof PurchaseUnit);
        $funding_source = $this->parsed_request_data['funding_source'] ?? '';
        $payer = $this->payer($this->parsed_request_data, $wc_order);
        $shipping_preference = $this->shipping_preference_factory->from_state($this->purchase_unit, $this->parsed_request_data['context'], WC()->cart, $funding_source, $wc_order);
        $action = in_array($this->parsed_request_data['context'], $this->pay_now_contexts, \true) ? ExperienceContext::USER_ACTION_PAY_NOW : ExperienceContext::USER_ACTION_CONTINUE;
        if ('card' === $funding_source) {
            if (CardBillingMode::MINIMAL_INPUT === $this->card_billing_data_mode) {
                if (ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS === $shipping_preference) {
                    if ($payer) {
                        $payer->set_address(null);
                    }
                }
                if (ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING === $shipping_preference) {
                    if ($payer) {
                        $payer->set_name(null);
                    }
                }
            }
            if (CardBillingMode::NO_WC === $this->card_billing_data_mode) {
                $payer = null;
            }
        }
        if ('venmo' === $funding_source) {
            $payment_source_key = 'venmo';
        } else {
            $payment_source_key = 'paypal';
        }
        $contact_preference = $this->contact_preference_factory->from_state($payment_source_key);
        $custom_args = array();
        $cart_data = null;
        // Save the cart to be able to access it after cross-browser AppSwitch.
        if ('pay-now' !== $data['context']) {
            try {
                $cart_data = $this->cart_data_factory->from_current_cart();
                $cart_data->generate_key();
                $key = $cart_data->key();
                assert(!empty($key));
                $custom_args[self::RETURN_URL_CART_QUERY_ARG] = $key;
            } catch (Throwable $exception) {
                $this->logger->error('Failed to serialize cart: ' . $exception->getMessage());
            }
        }
        $return_url = $this->return_url_factory->from_context($this->parsed_request_data['context'], $this->parsed_request_data, $custom_args);
        $experience_context = $this->experience_context_builder->with_default_paypal_config($shipping_preference, $action)->with_contact_preference($contact_preference)->with_custom_return_url($return_url)->with_custom_cancel_url($return_url);
        if ($this->should_handle_shipping_in_paypal($funding_source) && $this->server_side_shipping_callback_enabled && $shipping_preference === ExperienceContext::SHIPPING_PREFERENCE_GET_FROM_FILE) {
            $experience_context = $experience_context->with_shipping_callback();
        }
        $payment_source = new PaymentSource($payment_source_key, (object) array('experience_context' => $experience_context->build()->to_array()));
        try {
            $order = $this->api_endpoint->create(array($this->purchase_unit), $shipping_preference, $payer, $payment_method, $data, $payment_source);
        } catch (PayPalApiException $exception) {
            // Looks like currently there is no proper way to validate the shipping address for PayPal,
            // so we cannot make some invalid addresses null in PurchaseUnitFactory,
            // which causes failure e.g. for guests using the button on products pages when the country does not have postal codes.
            if (422 === $exception->status_code() && array_filter($exception->details(), function (stdClass $detail): bool {
                return isset($detail->field) && str_contains((string) $detail->field, 'shipping/address');
            })) {
                $this->logger->info('Invalid shipping address for order creation, retrying without it.');
                $this->purchase_unit->set_shipping(null);
                $order = $this->api_endpoint->create(array($this->purchase_unit), $shipping_preference, $payer, $payment_method, $data, $payment_source);
            } else {
                throw $exception;
            }
        }
        if ($cart_data) {
            try {
                $cart_data->set_paypal_order_id($order->id());
                $this->cart_data_transient_storage->save($cart_data);
            } catch (Throwable $exception) {
                $this->logger->error('Failed to save cart: ' . $exception->getMessage());
            }
        }
        return $order;
    }
    /**
     * Returns the Payer entity based on the request data.
     *
     * @param array         $data The request data.
     * @param WC_Order|null $wc_order The order.
     *
     * @return Payer|null
     */
    private function payer(array $data, ?WC_Order $wc_order = null)
    {
        if ('pay-now' === $data['context']) {
            $payer = $this->payer_factory->from_wc_order($wc_order);
            return $payer;
        }
        $payer = null;
        if (isset($data['payer']) && $data['payer']) {
            if (isset($data['payer']['phone']['phone_number']['national_number'])) {
                // make sure the phone number contains only numbers and is max 14. chars long.
                $number = $data['payer']['phone']['phone_number']['national_number'];
                $number = preg_replace('/[^0-9]/', '', $number);
                $number = substr($number, 0, 14);
                $data['payer']['phone']['phone_number']['national_number'] = $number;
                if (empty($data['payer']['phone']['phone_number']['national_number'])) {
                    unset($data['payer']['phone']);
                }
            }
            $payer = $this->payer_factory->from_paypal_response(json_decode(wp_json_encode($data['payer'])));
        }
        if (!$payer && $this->form) {
            if (isset($this->form['billing_email']) && '' !== $this->form['billing_email']) {
                return $this->payer_factory->from_checkout_form($this->form);
            }
        }
        return $payer;
    }
    /**
     * Sets the BN Code for the following request.
     *
     * @param array $data The request data.
     */
    private function set_bn_code(array $data)
    {
        $bn_code = isset($data['bn_code']) ? (string) $data['bn_code'] : '';
        if (!$bn_code) {
            return;
        }
        $this->session_handler->replace_bn_code($bn_code);
        $this->api_endpoint->with_bn_code($bn_code);
    }
    /**
     * Checks whether the form fields are valid.
     *
     * @param array $form_fields The form fields.
     * @throws ValidationException When fields are not valid.
     */
    private function validate_form(array $form_fields): void
    {
        try {
            $v = new CheckoutFormValidator();
            $v->validate($form_fields);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error("Form validation execution failed. {$exception->getMessage()} {$exception->getFile()}:{$exception->getLine()}");
        }
    }
    /**
     * Checks whether the terms input field is checked.
     *
     * @param array $form_fields The form fields.
     * @throws ValidationException When field is not checked.
     */
    private function validate_paynow_form(array $form_fields): void
    {
        if (isset($form_fields['terms-field']) && !isset($form_fields['terms'])) {
            throw new ValidationException(array(__('Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-paypal-payments')));
        }
    }
    /**
     * Returns the response data for success response.
     *
     * @param Order $order The PayPal order.
     * @return array
     */
    private function make_response(Order $order): array
    {
        return array('id' => $order->id(), 'custom_id' => $order->purchase_units()[0]->custom_id());
    }
    /**
     * Checks if the shipping should be handled in PayPal popup.
     *
     * @param string $funding_source The funding source.
     * @return bool true if the shipping should be handled in PayPal popup, otherwise false.
     */
    protected function should_handle_shipping_in_paypal(string $funding_source): bool
    {
        $is_vaulting_enabled = $this->settings->has('vault_enabled') && $this->settings->get('vault_enabled');
        if (!$this->handle_shipping_in_paypal) {
            return \false;
        }
        return !$is_vaulting_enabled || $funding_source !== 'venmo';
    }
}
