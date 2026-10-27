<?php

/**
 * Endpoint to verify if an order has been approved. An approved order
 * will be stored in the current session.
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderHelper;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\PayNowOrderHandler;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
/**
 * Class ApproveOrderEndpoint
 */
class ApproveOrderEndpoint implements EndpointInterface
{
    const ENDPOINT = 'ppc-approve-order';
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The session handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $api_endpoint;
    /**
     * The 3d secure helper object.
     *
     * @var ThreeDSecure
     */
    private $threed_secure;
    private SettingsProvider $settings_provider;
    private SettingsModel $settings_model;
    /**
     * The DCC applies object.
     *
     * @var DccApplies
     */
    private $dcc_applies;
    /**
     * The order helper.
     *
     * @var OrderHelper
     */
    protected $order_helper;
    protected PayNowOrderHandler $pay_now_order_handler;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    public function __construct(\WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData $request_data, OrderEndpoint $order_endpoint, SessionHandler $session_handler, ThreeDSecure $three_d_secure, SettingsProvider $settings_provider, SettingsModel $settings_model, DccApplies $dcc_applies, OrderHelper $order_helper, PayNowOrderHandler $pay_now_order_handler, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->api_endpoint = $order_endpoint;
        $this->session_handler = $session_handler;
        $this->threed_secure = $three_d_secure;
        $this->settings_provider = $settings_provider;
        $this->settings_model = $settings_model;
        $this->dcc_applies = $dcc_applies;
        $this->order_helper = $order_helper;
        $this->pay_now_order_handler = $pay_now_order_handler;
        $this->logger = $logger;
    }
    /**
     * The nonce.
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
     * @throws RuntimeException When order not found or handling failed.
     */
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request(self::nonce());
            if (!isset($data['order_id'])) {
                throw new RuntimeException('No order id given');
            }
            do_action('woocommerce_paypal_payments_approve_order_request_started', $data);
            $order = $this->api_endpoint->order($data['order_id']);
            $purchase_units = $order->purchase_units();
            if (!empty($purchase_units)) {
                $custom_id = $purchase_units[0]->custom_id();
                $prefix_len = strlen(CustomIds::CUSTOMER_ID_PREFIX);
                if (strpos($custom_id, CustomIds::CUSTOMER_ID_PREFIX) === 0) {
                    $order_session_id = substr($custom_id, $prefix_len);
                    $wc_session = WC()->session;
                    if ($wc_session instanceof \WC_Session_Handler) {
                        $current_session_id = (string) $wc_session->get_customer_unique_id();
                        if ($order_session_id !== $current_session_id) {
                            throw new RuntimeException(__('Order validation failed.', 'woocommerce-paypal-payments'));
                        }
                    }
                }
            }
            $payment_source = $order->payment_source();
            if ($payment_source && $payment_source->name() === 'card') {
                $disabled_cards = $this->settings_provider->disabled_cards();
                if (!empty($disabled_cards)) {
                    $card = strtolower($payment_source->properties()->brand ?? '');
                    if ('master_card' === $card) {
                        $card = 'mastercard';
                    }
                    if (!$this->dcc_applies->can_process_card($card) || in_array($card, $disabled_cards, \true)) {
                        throw new RuntimeException(__('Unfortunately, we do not accept this card.', 'woocommerce-paypal-payments'), 100);
                    }
                }
                // This check will either pass, or throw an exception.
                $this->verify_three_d_secure($order);
                $this->session_handler->replace_order($order);
                // Exit the request early.
                wp_send_json_success();
            }
            // Verify 3DS details. Throws an error when security check fails.
            $this->verify_three_d_secure($order);
            $is_ready = $order->status()->is(OrderStatus::APPROVED) || $order->status()->is(OrderStatus::CREATED);
            if (!$is_ready && $this->order_helper->contains_physical_goods($order)) {
                $message = sprintf(
                    // translators: %s is the id of the order.
                    __('Order %s is not ready for processing yet.', 'woocommerce-paypal-payments'),
                    $data['order_id']
                );
                $this->logger->log('error', $message);
                throw new RuntimeException($message);
            }
            $funding_source = $data['funding_source'] ?? null;
            $this->session_handler->replace_funding_source($funding_source);
            $this->session_handler->replace_order($order);
            // Pin chosen_payment_method to PayPal now so concurrent Store API cart requests can't reset it.
            if (WC()->session) {
                WC()->session->set('chosen_payment_method', PayPalGateway::ID);
            }
            if (apply_filters('woocommerce_paypal_payments_toggle_final_review_checkbox', \false)) {
                $this->toggle_final_review_enabled_setting();
            }
            if ($this->pay_now_order_handler->applies($data)) {
                $redirect_url = $this->pay_now_order_handler->pay($order, $data);
                wp_send_json_success(array('order_received_url' => $redirect_url));
            }
            wp_send_json_success();
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (Exception $error) {
            $this->logger->error('Order approve failed: ' . $error->getMessage());
            wp_send_json_error(array('name' => $error instanceof PayPalApiException ? $error->name() : '', 'message' => $error->getMessage(), 'code' => $error->getCode(), 'details' => $error instanceof PayPalApiException ? $error->details() : array()));
        }
    }
    /**
     * Will toggle the "final confirmation" checkbox.
     *
     * @return void
     */
    protected function toggle_final_review_enabled_setting(): void
    {
        $enable_pay_now = $this->settings_provider->enable_pay_now();
        $this->settings_model->set_enable_pay_now(!$enable_pay_now);
        $this->settings_model->save();
    }
    /**
     * Performs a 3DS check to verify the payment is not rejected from PayPal side.
     *
     * This method only checks, if the payment was rejected:
     *
     * - No 3DS details are present: The payment can proceed.
     * - 3DS details present but no rejected: Payment can proceed.
     * - 3DS details with a clear rejected: Payment fails.
     *
     * @param Order $order The PayPal order to inspect.
     * @throws RuntimeException When the 3DS check was rejected.
     */
    protected function verify_three_d_secure(Order $order): void
    {
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            // Missing 3DS details.
            return;
        }
        $proceed = ThreeDSecure::NO_DECISION;
        $order_status = $order->status();
        $source_name = $payment_source->name();
        /**
         * For GooglePay (and possibly other payment sources) we check the order
         * status, as it will clearly indicate if verification is needed.
         *
         * Note: PayPal is currently investigating this case.
         * Maybe the order status is wrong and should be ACCEPTED, in that case,
         * we could drop the condition and always run proceed_with_order().
         */
        if ($order_status->is(OrderStatus::PAYER_ACTION_REQUIRED)) {
            $proceed = $this->threed_secure->proceed_with_order($order);
        } elseif ('card' === $source_name) {
            // For credit cards, we also check the 3DS response.
            $proceed = $this->threed_secure->proceed_with_order($order);
        }
        // Handle the verification result based on the proceed value.
        switch ($proceed) {
            case ThreeDSecure::PROCEED:
                // Check was successful.
                return;
            case ThreeDSecure::NO_DECISION:
                // No rejection. Let's proceed with the payment.
                return;
            case ThreeDSecure::RETRY:
                // Rejection case 1, verification can be retried.
                throw new RuntimeException(__('Something went wrong. Please try again.', 'woocommerce-paypal-payments'));
            case ThreeDSecure::REJECT:
                // Rejection case 2, payment was rejected.
                throw new RuntimeException(__('Unfortunately, we can\'t accept your card. Please choose a different payment method.', 'woocommerce-paypal-payments'));
        }
    }
}
