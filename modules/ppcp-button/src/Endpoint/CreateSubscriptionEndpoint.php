<?php

/**
 * Endpoint to create a PayPal Subscription on the server.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\SubscriptionCustomId;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
/**
 * Creates the PayPal Subscription that the SDK v6 subscription session starts
 * with. v5 created it in the browser via actions.subscription.create(); v6
 * expects the subscription ID from the merchant server instead.
 *
 * The plan is resolved here rather than taken from the request, so the
 * browser cannot choose which plan is subscribed to.
 */
class CreateSubscriptionEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    const ENDPOINT = 'ppc-create-subscription';
    private RequestData $request_data;
    private BillingSubscriptions $billing_subscriptions;
    private SubscriptionHelper $subscription_helper;
    private LoggerInterface $logger;
    /**
     * Resolves the current subscriptions mode ('subscriptions_api', 'vaulting_api', …).
     *
     * @var callable(): string
     */
    private $get_subscriptions_mode;
    public function __construct(RequestData $request_data, BillingSubscriptions $billing_subscriptions, SubscriptionHelper $subscription_helper, callable $get_subscriptions_mode, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->billing_subscriptions = $billing_subscriptions;
        $this->subscription_helper = $subscription_helper;
        $this->get_subscriptions_mode = $get_subscriptions_mode;
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
     * The request may carry `product_id` (and `variation_id`) for the single
     * product page, whose product is not in the cart yet when the subscription
     * is created. Without them, the plan is resolved from the cart.
     *
     * @throws RuntimeException When subscriptions are disabled or no plan is found; caught and sent as an error response.
     */
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            if (SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS !== ($this->get_subscriptions_mode)()) {
                throw new RuntimeException('PayPal Subscriptions are not enabled');
            }
            $plan_id = $this->plan_id($data);
            if (!$plan_id) {
                throw new RuntimeException('No PayPal subscription plan found');
            }
            $subscription = $this->billing_subscriptions->create($plan_id, SubscriptionCustomId::for_current_session());
            wp_send_json_success(array('subscription_id' => (string) $subscription->id));
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (Exception $error) {
            $this->logger->error('Subscription create failed: ' . $error->getMessage());
            wp_send_json_error(array('name' => $error instanceof PayPalApiException ? $error->name() : '', 'message' => $error->getMessage(), 'code' => $error->getCode(), 'details' => $error instanceof PayPalApiException ? $error->details() : (object) array()));
        }
    }
    /**
     * Resolves the plan in the same order ApproveSubscriptionEndpoint expects it,
     * so a subscription created here passes its plan check.
     *
     * @param array $data The request data.
     */
    private function plan_id(array $data): string
    {
        $product_id = absint($data['product_id'] ?? 0);
        if ($product_id) {
            return $this->product_plan_id($product_id, absint($data['variation_id'] ?? 0));
        }
        $plan_id = $this->subscription_helper->paypal_subscription_variation_from_cart();
        if (!$plan_id) {
            $plan_id = $this->subscription_helper->paypal_subscription_id();
        }
        return $plan_id;
    }
    /**
     * The plan of a purchasable subscription product, or of one of its variations.
     *
     * @param int $product_id   The product ID.
     * @param int $variation_id The variation ID, 0 for a simple subscription.
     */
    private function product_plan_id(int $product_id, int $variation_id): string
    {
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product instanceof WC_Product || !$product->is_purchasable()) {
            return '';
        }
        if ($variation_id && $product->get_parent_id() !== $product_id) {
            return '';
        }
        $plan = $product->get_meta('ppcp_subscription_plan');
        return is_array($plan) ? (string) ($plan['id'] ?? '') : '';
    }
}
