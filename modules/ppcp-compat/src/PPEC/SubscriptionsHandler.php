<?php

/**
 * Compatibility layer for subscriptions paid via PayPal Express Checkout.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Automattic\WooCommerce\Utilities\OrderUtil;
use stdClass;
use WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
/**
 * Handles renewals and edit/display matters for subscriptions renewed via PayPal Express Checkout.
 */
class SubscriptionsHandler
{
    const BILLING_AGREEMENT_TOKEN_TYPE = 'BILLING_AGREEMENT';
    /**
     * PayPal Payments subscription renewal handler.
     *
     * @var RenewalHandler
     */
    private $ppcp_renewal_handler;
    /**
     * Mock gateway instance.
     *
     * @var MockGateway
     */
    private $mock_gateway;
    /**
     * Constructor.
     *
     * @param RenewalHandler $ppcp_renewal_handler PayPal Payments Subscriptions renewal handler.
     * @param MockGateway    $gateway              Mock gateway instance.
     */
    public function __construct(RenewalHandler $ppcp_renewal_handler, \WooCommerce\PayPalCommerce\Compat\PPEC\MockGateway $gateway)
    {
        $this->ppcp_renewal_handler = $ppcp_renewal_handler;
        $this->mock_gateway = $gateway;
    }
    /**
     * Sets up hooks.
     *
     * @return void
     */
    public function maybe_hook()
    {
        if (!\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::use_ppec_compat_layer_for_subscriptions()) {
            return;
        }
        // "Mock" PPEC when needed.
        add_filter('woocommerce_payment_gateways', array($this, 'add_mock_ppec_gateway'));
        // Add billing agreement as a valid token type.
        add_filter('woocommerce_paypal_payments_valid_payment_token_types', array($this, 'add_billing_agreement_as_token_type'));
        // Process PPEC renewals through PayPal Payments.
        add_action('woocommerce_scheduled_subscription_payment_' . \WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::PPEC_GATEWAY_ID, array($this, 'process_renewal'), 10, 2);
    }
    /**
     * Adds a mock gateway to disguise as PPEC when needed. Hooked onto `woocommerce_payment_gateways`.
     * The mock gateway fixes display issues where subscriptions paid via PPEC appear as "via Manual Renewal" and also
     * prevents subscriptions from automatically changing the payment method to "manual" when a subscription is edited.
     *
     * @param array $gateways List of gateways.
     * @return array
     */
    public function add_mock_ppec_gateway($gateways)
    {
        if (!isset($gateways[\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::PPEC_GATEWAY_ID])) {
            $gateways[\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::PPEC_GATEWAY_ID] = $this->mock_gateway;
        }
        return $gateways;
    }
    /**
     * Registers BILLING_AGREEMENT as a valid token type for using with the PayPal REST API.
     *
     * @param array $types List of token types.
     * @return array
     */
    public function add_billing_agreement_as_token_type($types)
    {
        if (!in_array(self::BILLING_AGREEMENT_TOKEN_TYPE, $types, \true)) {
            $types[] = self::BILLING_AGREEMENT_TOKEN_TYPE;
        }
        return $types;
    }
    /**
     * Processes subscription renewals on behalf of PayPal Express Checkout.
     * Hooked onto `woocommerce_scheduled_subscription_payment_ppec_paypal`.
     *
     * @param float     $amount The order amount.
     * @param \WC_Order $order  The renewal order.
     * @return void
     */
    public function process_renewal($amount, $order)
    {
        add_filter('woocommerce_paypal_payments_subscriptions_get_token_for_customer', array($this, 'use_billing_agreement_as_token'), 10, 3);
        $this->ppcp_renewal_handler->renew($order);
        remove_filter('woocommerce_paypal_payments_subscriptions_get_token_for_customer', array($this, 'use_billing_agreement_as_token'));
    }
    /**
     * Short-circuits `RenewalHandler::get_token_for_customer()` to use a Billing Agreement ID for PPEC orders
     * instead of vaulted tokens.
     *
     * @param null|PaymentToken $token    Current token value.
     * @param \WC_Customer      $customer Customer object.
     * @param \WC_Order         $order    Renewal order.
     * @return null|PaymentToken
     */
    public function use_billing_agreement_as_token($token, $customer, $order)
    {
        if (\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::PPEC_GATEWAY_ID === $order->get_payment_method() && wcs_order_contains_renewal($order)) {
            $billing_agreement_id = $order->get_meta('_ppec_billing_agreement_id', \true);
            if ($billing_agreement_id) {
                return new PaymentToken($billing_agreement_id, new stdClass(), 'BILLING_AGREEMENT');
            }
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
            if (!empty($subscriptions)) {
                $subscription = reset($subscriptions);
                // Get first subscription.
                $parent_order = $subscription->get_parent();
                if ($parent_order) {
                    $billing_agreement_id = $parent_order->get_meta('_ppec_billing_agreement_id', \true);
                    if ($billing_agreement_id) {
                        return new PaymentToken($billing_agreement_id, new stdClass(), 'BILLING_AGREEMENT');
                    }
                }
            }
        }
        return $token;
    }
}
