<?php

/**
 * Service for checking payment method tokens.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Service;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
class PaymentMethodTokensChecker
{
    /**
     * Payment method tokens endpoint.
     *
     * @var PaymentTokensEndpoint
     */
    private PaymentTokensEndpoint $payment_method_tokens_endpoint;
    public function __construct(PaymentTokensEndpoint $payment_method_tokens_endpoint)
    {
        $this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
    }
    /**
     * Checks if customer has a saved PayPal payment token.
     *
     * @param string $customer_id PayPal customer ID.
     * @return bool
     */
    public function has_paypal_payment_token(string $customer_id): bool
    {
        if (!$customer_id) {
            return \false;
        }
        try {
            $tokens = $this->payment_method_tokens_endpoint->payment_tokens_for_customer($customer_id);
            foreach ($tokens as $token) {
                $payment_source = $token['payment_source']->name() ?? '';
                if ($payment_source === 'paypal') {
                    return \true;
                }
            }
        } catch (RuntimeException $e) {
            return \false;
        }
        return \false;
    }
}
