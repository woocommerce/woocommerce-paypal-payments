<?php

/**
 * Compatibility layer for subscriptions paid via PayPal Express Checkout.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
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
    private \WooCommerce\PayPalCommerce\Compat\PPEC\BillingAgreementTokenConverter $token_converter;
    private LoggerInterface $logger;
    private bool $vault_v3_eligible;
    public function __construct(RenewalHandler $ppcp_renewal_handler, \WooCommerce\PayPalCommerce\Compat\PPEC\MockGateway $gateway, \WooCommerce\PayPalCommerce\Compat\PPEC\BillingAgreementTokenConverter $token_converter, LoggerInterface $logger, bool $vault_v3_eligible = \false)
    {
        $this->ppcp_renewal_handler = $ppcp_renewal_handler;
        $this->mock_gateway = $gateway;
        $this->token_converter = $token_converter;
        $this->logger = $logger;
        $this->vault_v3_eligible = $vault_v3_eligible;
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
     * Short-circuits `RenewalHandler::get_token_for_customer()` for PPEC orders.
     *
     * Tries the vault v3 conversion path first. If that is not applicable or fails,
     * falls back to the legacy BILLING_AGREEMENT token path.
     */
    public function use_billing_agreement_as_token($token, $customer, $order)
    {
        if (\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::PPEC_GATEWAY_ID !== $order->get_payment_method() || !wcs_order_contains_renewal($order)) {
            return $token;
        }
        if ($this->vault_v3_eligible) {
            $vault_token = $this->get_vault_v3_token($order);
            if ($vault_token) {
                return $vault_token;
            }
        }
        return $this->get_billing_agreement_token($order) ?? $token;
    }
    /**
     * Attempts to resolve or create a Vault v3 payment token for the renewal order.
     *
     * Checks if the subscription already has a converted vault token. If not,
     * attempts conversion from the billing agreement via the PayPal Vault v3 API.
     */
    private function get_vault_v3_token(\WC_Order $order): ?PaymentToken
    {
        $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
        $subscription = !empty($subscriptions) ? reset($subscriptions) : null;
        if (!$subscription) {
            return null;
        }
        $vault_token_id = $subscription->get_meta('_ppec_ba_converted_to_vault_v3', \true);
        if ($vault_token_id) {
            return new PaymentToken($vault_token_id, new stdClass(), PaymentToken::TYPE_PAYMENT_METHOD_TOKEN);
        }
        $billing_agreement_id = $this->resolve_billing_agreement_id($order);
        if (!$billing_agreement_id) {
            return null;
        }
        $vault_token_id = $this->token_converter->convert($billing_agreement_id, $order->get_customer_id());
        if (!$vault_token_id) {
            return null;
        }
        $subscription->update_meta_data('_ppec_ba_converted_to_vault_v3', $vault_token_id);
        $subscription->save();
        $this->logger->info(sprintf('Subscription #%d: converted Billing Agreement %s to Vault v3 token %s.', $subscription->get_id(), $billing_agreement_id, $vault_token_id));
        return new PaymentToken($vault_token_id, new stdClass(), PaymentToken::TYPE_PAYMENT_METHOD_TOKEN);
    }
    private function get_billing_agreement_token(\WC_Order $order): ?PaymentToken
    {
        $billing_agreement_id = $this->resolve_billing_agreement_id($order);
        if (!$billing_agreement_id) {
            return null;
        }
        return new PaymentToken($billing_agreement_id, new stdClass(), 'BILLING_AGREEMENT');
    }
    private function resolve_billing_agreement_id(\WC_Order $order): ?string
    {
        $billing_agreement_id = $order->get_meta('_ppec_billing_agreement_id', \true);
        if ($billing_agreement_id) {
            return $billing_agreement_id;
        }
        $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
        if (!empty($subscriptions)) {
            $subscription = reset($subscriptions);
            $parent_order = $subscription->get_parent();
            if ($parent_order) {
                $billing_agreement_id = $parent_order->get_meta('_ppec_billing_agreement_id', \true);
                if ($billing_agreement_id) {
                    return $billing_agreement_id;
                }
            }
        }
        return null;
    }
}
