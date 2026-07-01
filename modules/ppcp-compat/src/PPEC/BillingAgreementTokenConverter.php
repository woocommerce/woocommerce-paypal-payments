<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
class BillingAgreementTokenConverter
{
    private PaymentMethodTokensEndpoint $payment_method_tokens_endpoint;
    private CustomerRepository $customer_repository;
    private LoggerInterface $logger;
    public function __construct(PaymentMethodTokensEndpoint $payment_method_tokens_endpoint, CustomerRepository $customer_repository, LoggerInterface $logger)
    {
        $this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
        $this->customer_repository = $customer_repository;
        $this->logger = $logger;
    }
    /**
     * @return string|null The vault token ID on success, null on failure.
     */
    public function convert(string $billing_agreement_id, int $user_id): ?string
    {
        try {
            $payment_source = new PaymentSource('token', (object) array('id' => $billing_agreement_id, 'type' => 'BILLING_AGREEMENT'));
            $customer_id = $this->customer_repository->customer_id_for_user($user_id);
            $result = $this->payment_method_tokens_endpoint->create_payment_token($payment_source, $customer_id);
            if (empty($result->id)) {
                $this->logger->error(sprintf('Vault token creation for Billing Agreement %s returned no token ID.', $billing_agreement_id));
                return null;
            }
            if (isset($result->customer->id)) {
                update_user_meta($user_id, '_ppcp_target_customer_id', $result->customer->id);
            }
            $this->logger->info(sprintf('Successfully converted Billing Agreement %s to Vault v3 token %s for user %d.', $billing_agreement_id, $result->id, $user_id));
            return $result->id;
        } catch (Exception $exception) {
            $this->logger->error(sprintf('Failed to convert Billing Agreement %s to Vault v3 token for user %d: %s', $billing_agreement_id, $user_id, $exception->getMessage()));
            return null;
        }
    }
}
