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
    public const CACHE_KEY = 'ppcp_reference_transaction_enabled';
    protected PartnersEndpoint $partners_endpoint;
    protected \WooCommerce\PayPalCommerce\ApiClient\Helper\Cache $cache;
    public function __construct(PartnersEndpoint $partners_endpoint, \WooCommerce\PayPalCommerce\ApiClient\Helper\Cache $cache)
    {
        $this->partners_endpoint = $partners_endpoint;
        $this->cache = $cache;
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
        if ($this->cache->has(self::CACHE_KEY)) {
            return (bool) $this->cache->get(self::CACHE_KEY);
        }
        try {
            foreach ($this->partners_endpoint->seller_status()->capabilities() as $capability) {
                if ($capability->name() === 'PAYPAL_WALLET_VAULTING_ADVANCED' && $capability->status() === 'ACTIVE') {
                    $this->cache->set(self::CACHE_KEY, \true, MONTH_IN_SECONDS);
                    return \true;
                }
            }
        } catch (RuntimeException $exception) {
            $this->cache->set(self::CACHE_KEY, \false, HOUR_IN_SECONDS);
            return \false;
        }
        $this->cache->set(self::CACHE_KEY, \false, HOUR_IN_SECONDS);
        return \false;
    }
}
