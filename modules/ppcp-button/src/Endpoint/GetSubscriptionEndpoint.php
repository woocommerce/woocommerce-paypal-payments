<?php

/**
 * Endpoint to read the subscriber of an approved PayPal Subscription.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\SubscriptionCustomId;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
/**
 * Returns the subscriber's name, email and shipping address, so the block
 * checkout can fill in the buyer's details before the subscription is approved
 * into the session: approving stores the cart hash, which an address change
 * made afterwards could invalidate.
 *
 * Only a subscription bound to the current shopper session is answered, since
 * the response carries the subscriber's personal data.
 */
class GetSubscriptionEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    const ENDPOINT = 'ppc-get-subscription';
    private RequestData $request_data;
    private BillingSubscriptions $billing_subscriptions;
    private LoggerInterface $logger;
    public function __construct(RequestData $request_data, BillingSubscriptions $billing_subscriptions, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->billing_subscriptions = $billing_subscriptions;
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
     * @throws RuntimeException When no subscription id is given or the subscription belongs to
     *                          another session; caught and sent as an error response.
     */
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $subscription_id = (string) ($data['subscription_id'] ?? '');
            if ('' === $subscription_id) {
                throw new RuntimeException('No subscription id given');
            }
            $subscription = $this->billing_subscriptions->subscription($subscription_id);
            // Strict, unlike the approval's ownership check: subscriptions without
            // the session prefix are not answered at all.
            $custom_id = SubscriptionCustomId::for_current_session();
            if ('' === $custom_id || ($subscription->custom_id ?? '') !== $custom_id) {
                throw new RuntimeException(__('Subscription validation failed.', 'woocommerce-paypal-payments'));
            }
            $subscriber = $subscription->subscriber ?? new \stdClass();
            wp_send_json_success(array('subscriber' => array('name' => $subscriber->name ?? null, 'email_address' => $subscriber->email_address ?? null, 'shipping_address' => $subscriber->shipping_address ?? null)));
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (Exception $error) {
            $this->logger->error('Get subscription failed: ' . $error->getMessage());
            wp_send_json_error(array('message' => $error->getMessage()));
        }
    }
}
