<?php

/**
 * Reference transaction status helper class.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class ReferenceTransactionStatus
 *
 * Helper class to check reference transaction capabilities for PayPal merchant accounts.
 */
class ReferenceTransactionStatus
{
    protected PartnersEndpoint $partners_endpoint;
    public function __construct(PartnersEndpoint $partners_endpoint)
    {
        $this->partners_endpoint = $partners_endpoint;
    }
    /**
     * Checks if reference transactions are enabled in the merchant account.
     *
     * This method verifies if the merchant has the PAYPAL_WALLET_VAULTING_ADVANCED
     * capability active, which is required for processing reference transactions.
     *
     * @return bool True if reference transactions are enabled, false otherwise.
     */
    public function reference_transaction_enabled(): bool
    {
        try {
            foreach ($this->partners_endpoint->seller_status()->capabilities() as $capability) {
                if ($capability->name() === 'PAYPAL_WALLET_VAULTING_ADVANCED' && $capability->status() === 'ACTIVE') {
                    return \true;
                }
            }
        } catch (RuntimeException $exception) {
            return \false;
        }
        return \false;
    }
}
