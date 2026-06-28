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
use WC_Session_Handler;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
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
            $paypal_subscription_id = $data['subscription_id'] ?? '';
            $subscription = $paypal_subscription_id ? $this->validate_subscription($paypal_subscription_id) : null;
            // Ensure the PayPal order belongs to the current session before it is stored.
            $order = $this->order_endpoint->order($data['order_id']);
            if ($subscription) {
                // Bind the order to the validated subscription: the order must have been
                // approved by the same payer that owns the subscription.
                $this->validate_order_belongs_to_subscriber($order, $subscription);
            } else {
                $purchase_units = $order->purchase_units();
                if (!empty($purchase_units)) {
                    $this->validate_custom_id_ownership((string) $purchase_units[0]->custom_id());
                }
            }
            $this->session_handler->replace_order($order);
            if ($paypal_subscription_id) {
                WC()->session->set('ppcp_subscription_id', $paypal_subscription_id);
                WC()->session->set('ppcp_subscription_cart_hash', WC()->cart->get_cart_hash());
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
     * Validates subscription status, ownership and plan ID.
     *
     * @param string $subscription_id The PayPal subscription ID.
     * @return \stdClass The validated PayPal subscription.
     * @throws RuntimeException When the subscription status is invalid, it does not belong
     *                          to the current session, or the plan ID doesn't match.
     */
    private function validate_subscription(string $subscription_id): \stdClass
    {
        $subscription = $this->billing_subscriptions->subscription($subscription_id);
        $status = $subscription->status ?? '';
        if (!in_array($status, self::VALID_SUBSCRIPTION_STATUSES, \true)) {
            throw new RuntimeException("Invalid subscription status: {$status}");
        }
        /*
         * The subscription must carry the custom_id we injected into
         * actions.subscription.create() for the current session. A subscription created in a
         * different session carries a different custom_id and is rejected before replace_order().
         */
        $this->validate_custom_id_ownership((string) ($subscription->custom_id ?? ''));
        $plan_id = $subscription->plan_id ?? '';
        $expected_plan_id = $this->subscription_helper->paypal_subscription_variation_from_cart();
        if (!$expected_plan_id) {
            $expected_plan_id = $this->subscription_helper->paypal_subscription_id();
        }
        if (!$plan_id || !$expected_plan_id || $plan_id !== $expected_plan_id) {
            throw new RuntimeException('Subscription plan ID does not match any cart product plan');
        }
        return $subscription;
    }
    /**
     * Binds the supplied PayPal order to the validated subscription.
     *
     * The order's payer must match the subscription's subscriber. A different order_id swapped
     * into the request was approved by another PayPal account, so its payer will not match and
     * the order is rejected before it is stored in the session.
     *
     * @param Order     $order The PayPal order fetched from the supplied order_id.
     * @param \stdClass $subscription The validated PayPal subscription.
     * @throws RuntimeException When the order was not approved by the subscription's subscriber.
     */
    private function validate_order_belongs_to_subscriber(Order $order, \stdClass $subscription): void
    {
        $subscriber_payer_id = (string) ($subscription->subscriber->payer_id ?? '');
        $payer = $order->payer();
        $order_payer_id = $payer ? $payer->payer_id() : '';
        if ('' === $subscriber_payer_id || '' === $order_payer_id || $order_payer_id !== $subscriber_payer_id) {
            throw new RuntimeException(__('Order validation failed.', 'woocommerce-paypal-payments'));
        }
    }
    /**
     * Rejects a PayPal custom_id that is bound to a different shopper session.
     *
     * Mirrors the ownership check in ApproveOrderEndpoint: only custom_id values that
     * carry our session prefix (see PurchaseUnitFactory::from_wc_cart() and the
     * custom_id injected into actions.subscription.create()) are validated, so
     * subscriptions/orders created without the prefix (e.g. older cached scripts) are
     * left untouched.
     *
     * @param string $custom_id The custom_id from the PayPal subscription or order.
     * @throws RuntimeException When the custom_id belongs to a different session.
     */
    private function validate_custom_id_ownership(string $custom_id): void
    {
        if (strpos($custom_id, CustomIds::CUSTOMER_ID_PREFIX) !== 0) {
            return;
        }
        $wc_session = WC()->session;
        if (!$wc_session instanceof WC_Session_Handler) {
            return;
        }
        $bound_session_id = substr($custom_id, strlen(CustomIds::CUSTOMER_ID_PREFIX));
        $current_session_id = (string) $wc_session->get_customer_unique_id();
        if ($bound_session_id !== $current_session_id) {
            throw new RuntimeException(__('Order validation failed.', 'woocommerce-paypal-payments'));
        }
    }
}
