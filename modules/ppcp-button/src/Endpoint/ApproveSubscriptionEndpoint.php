<?php

/**
 * Endpoint to handle PayPal Subscription created.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
/**
 * Class ApproveSubscriptionEndpoint
 */
class ApproveSubscriptionEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    const ENDPOINT = 'ppc-approve-subscription';
    const VALID_SUBSCRIPTION_STATUSES = array('ACTIVE', 'APPROVED');
    /**
     * Helper providing context: is current page is checkout, what type of checkout it is, etc.
     *
     * @var Context $context
     */
    private Context $context;
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The session handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * Whether the final review is enabled.
     *
     * @var bool
     */
    protected $final_review_enabled;
    /**
     * The WooCommerce order creator.
     *
     * @var WooCommerceOrderCreator
     */
    protected $wc_order_creator;
    /**
     * The WC gateway.
     *
     * @var PayPalGateway
     */
    protected $gateway;
    private BillingSubscriptions $billing_subscriptions;
    private LoggerInterface $logger;
    private SubscriptionHelper $subscription_helper;
    public function __construct(\WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data, OrderEndpoint $order_endpoint, SessionHandler $session_handler, bool $final_review_enabled, WooCommerceOrderCreator $wc_order_creator, PayPalGateway $gateway, Context $context, BillingSubscriptions $billing_subscriptions, LoggerInterface $logger, SubscriptionHelper $subscription_helper)
    {
        $this->request_data = $request_data;
        $this->order_endpoint = $order_endpoint;
        $this->session_handler = $session_handler;
        $this->final_review_enabled = $final_review_enabled;
        $this->wc_order_creator = $wc_order_creator;
        $this->gateway = $gateway;
        $this->context = $context;
        $this->billing_subscriptions = $billing_subscriptions;
        $this->logger = $logger;
        $this->subscription_helper = $subscription_helper;
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
            $data = $this->request_data->read_request($this->nonce());
            if (!isset($data['order_id'])) {
                throw new RuntimeException('No order id given');
            }
            $order = $this->order_endpoint->order($data['order_id']);
            $this->session_handler->replace_order($order);
            $paypal_subscription_id = $data['subscription_id'];
            if (isset($paypal_subscription_id)) {
                $this->validate_subscription($paypal_subscription_id);
                WC()->session->set('ppcp_subscription_id', $paypal_subscription_id);
            }
            $should_create_wc_order = $data['should_create_wc_order'] ?? \false;
            if (!$this->final_review_enabled && !$this->context->is_checkout() && $should_create_wc_order) {
                $wc_order = $this->wc_order_creator->create_from_paypal_order($order, WC()->cart);
                $this->gateway->process_payment($wc_order->get_id());
                $order_received_url = $wc_order->get_checkout_order_received_url();
                wp_send_json_success(array('order_received_url' => $order_received_url));
            }
            wp_send_json_success();
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (Exception $error) {
            $this->logger->error('Subscription approve failed: ' . $error->getMessage());
            wp_send_json_error(array('name' => $error instanceof PayPalApiException ? $error->name() : '', 'message' => $error->getMessage(), 'code' => $error->getCode(), 'details' => $error instanceof PayPalApiException ? $error->details() : (object) array()));
        }
    }
    /**
     * Validates subscription status and plan ID.
     *
     * @param string $subscription_id The PayPal subscription ID.
     * @throws RuntimeException When subscription status is invalid or plan ID doesn't match.
     */
    private function validate_subscription(string $subscription_id): void
    {
        $subscription = $this->billing_subscriptions->subscription($subscription_id);
        $status = $subscription->status ?? '';
        if (!in_array($status, self::VALID_SUBSCRIPTION_STATUSES, \true)) {
            throw new RuntimeException("Invalid subscription status: {$status}");
        }
        $plan_id = $subscription->plan_id ?? '';
        $expected_plan_id = $this->subscription_helper->paypal_subscription_variation_from_cart();
        if (!$expected_plan_id) {
            $expected_plan_id = $this->subscription_helper->paypal_subscription_id();
        }
        if (!$plan_id || !$expected_plan_id || $plan_id !== $expected_plan_id) {
            throw new RuntimeException('Subscription plan ID does not match any cart product plan');
        }
    }
}
