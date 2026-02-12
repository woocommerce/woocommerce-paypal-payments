<?php

/**
 * Handles PayPal subscription status.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class SubscriptionStatus
 */
class SubscriptionStatus
{
    /**
     * Billing subscriptions endpoint.
     *
     * @var BillingSubscriptions
     */
    private $subscriptions_endpoint;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * SubscriptionStatus constructor.
     *
     * @param BillingSubscriptions $subscriptions_endpoint Billing subscriptions endpoint.
     * @param LoggerInterface      $logger The logger.
     */
    public function __construct(BillingSubscriptions $subscriptions_endpoint, LoggerInterface $logger)
    {
        $this->subscriptions_endpoint = $subscriptions_endpoint;
        $this->logger = $logger;
    }
    /**
     * Updates PayPal subscription status from the given WC Subscription status.
     *
     * @param string $subscription_status The WC Subscription status.
     * @param string $subscription_id The PayPal Subscription ID.
     * @return void
     */
    public function update_status(string $subscription_status, string $subscription_id): void
    {
        if ($subscription_status === 'cancelled') {
            try {
                $current_subscription = $this->subscriptions_endpoint->subscription($subscription_id);
                if ($current_subscription->status === 'CANCELLED') {
                    return;
                }
                $this->logger->info(sprintf('Canceling PayPal subscription #%s.', $subscription_id));
                $this->subscriptions_endpoint->cancel($subscription_id);
            } catch (RuntimeException $exception) {
                $this->logger->error(sprintf('Could not cancel PayPal subscription #%s. %s', $subscription_id, $this->get_error($exception)));
            }
        }
        if ($subscription_status === 'on-hold' || $subscription_status === 'pending-cancel') {
            try {
                $this->logger->info(sprintf('Suspending PayPal subscription #%s.', $subscription_id));
                $this->subscriptions_endpoint->suspend($subscription_id);
            } catch (RuntimeException $exception) {
                $this->logger->error(sprintf('Could not suspend PayPal subscription #%s. %s', $subscription_id, $this->get_error($exception)));
            }
        }
        if ($subscription_status === 'active') {
            try {
                $current_subscription = $this->subscriptions_endpoint->subscription($subscription_id);
                if ($current_subscription->status === 'SUSPENDED') {
                    $this->logger->info(sprintf('Activating suspended PayPal subscription #%s.', $subscription_id));
                    $this->subscriptions_endpoint->activate($subscription_id);
                }
            } catch (RuntimeException $exception) {
                $this->logger->error(sprintf('Could not reactivate PayPal subscription #%s. %s', $subscription_id, $this->get_error($exception)));
            }
        }
    }
    /**
     * Get status by subscription ID.
     *
     * @param string $subscription_id Subscription ID to get status for.
     * @return string Current subscription status.
     *
     * @throws RuntimeException If the request fails.
     */
    public function get_status(string $subscription_id): string
    {
        static $subscription_status = null;
        if (null === $subscription_status) {
            $subscription = $this->subscriptions_endpoint->subscription($subscription_id);
            if (!isset($subscription->status)) {
                throw new RuntimeException('Status not found in subscription data');
            }
            $subscription_status = (string) $subscription->status;
        }
        return $subscription_status;
    }
    /**
     * Get error from exception.
     *
     * @param RuntimeException $exception The exception.
     * @return string
     */
    private function get_error(RuntimeException $exception): string
    {
        $error = $exception->getMessage();
        if (is_a($exception, PayPalApiException::class)) {
            $error = $exception->get_details($error);
        }
        return $error;
    }
}
