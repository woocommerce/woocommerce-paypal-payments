<?php

namespace WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
class VaultedPayPalEmail
{
    private ?array $payment_tokens = null;
    private PaymentTokensEndpoint $payment_tokens_endpoint;
    private PaymentTokenRepository $payment_token_repository;
    private LoggerInterface $logger;
    public function __construct(PaymentTokensEndpoint $payment_tokens_endpoint, PaymentTokenRepository $payment_token_repository, LoggerInterface $logger)
    {
        $this->payment_tokens_endpoint = $payment_tokens_endpoint;
        $this->payment_token_repository = $payment_token_repository;
        $this->logger = $logger;
    }
    /**
     * Returns the vaulted PayPal email or empty string.
     *
     * @return string
     */
    public function get_vaulted_paypal_email(): string
    {
        try {
            $customer_id = get_user_meta(get_current_user_id(), '_ppcp_target_customer_id', \true);
            if ($customer_id) {
                $customer_tokens = $this->payment_tokens_endpoint->payment_tokens_for_customer($customer_id);
                foreach ($customer_tokens as $token) {
                    $email_address = $token['payment_source']->properties()->email_address ?? '';
                    if ($email_address) {
                        return $email_address;
                    }
                }
            }
            $tokens = $this->get_payment_tokens();
            foreach ($tokens as $token) {
                if (isset($token->source()->paypal)) {
                    return $token->source()->paypal->payer->email_address;
                }
            }
        } catch (Exception $exception) {
            $this->logger->error('Failed to get PayPal vaulted email. ' . $exception->getMessage());
        }
        return '';
    }
    /**
     * Retrieves all payment tokens for the user, via API or cached if already queried.
     *
     * @return PaymentToken[]
     */
    private function get_payment_tokens(): array
    {
        if (null === $this->payment_tokens) {
            $this->payment_tokens = $this->payment_token_repository->all_for_user_id(get_current_user_id());
        }
        return $this->payment_tokens;
    }
}
