<?php

/**
 * Eligibility status.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use RuntimeException;
use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
/**
 * Class ProductStatus
 *
 * Base class to check the eligibility of a product for the current merchant.
 */
abstract class ProductStatus
{
    /**
     * The product key, must be declared by the implementing class.
     */
    public const KEY = '';
    public const STATE_IS_ENABLED = 'yes';
    public const STATE_IS_DISABLED = 'no';
    /**
     * Caches the SellerStatus API response to avoid duplicate API calls
     * during the same request.
     */
    private static ?SellerStatus $seller_status = null;
    private ?bool $is_eligible = null;
    private bool $has_request_failure = \false;
    /**
     * Whether the merchant onboarding process was completed and the
     * merchant API is available.
     */
    private bool $is_connected;
    private PartnersEndpoint $partners_endpoint;
    private \WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry $api_failure_registry;
    protected \WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatusResultCache $result_cache;
    public function __construct(bool $is_connected, PartnersEndpoint $partners_endpoint, \WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry $api_failure_registry, \WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatusResultCache $result_cache)
    {
        $this->is_connected = $is_connected;
        $this->partners_endpoint = $partners_endpoint;
        $this->api_failure_registry = $api_failure_registry;
        $this->result_cache = $result_cache;
    }
    /**
     * Decides, if the specific product (feature) is available for the authenticated
     * merchant.
     *
     * The availability is determined by inspecting the SellerStatus response
     * returned by the API. On first check, the result is cached to avoid calling the
     * API again on subsequent checks.
     *
     * In case the onboarding is not completed, the feature is marked as not-available
     * without caching it, to fetch a fresh value directly after login.
     */
    public function is_active(): bool
    {
        if (is_bool($this->is_eligible)) {
            return $this->is_eligible;
        }
        $this->is_eligible = \false;
        $this->has_request_failure = \false;
        if (!$this->is_onboarded()) {
            return \false;
        }
        $local_state = $this->check_local_state();
        if (is_bool($local_state)) {
            $this->is_eligible = $local_state;
            return $local_state;
        }
        // Check using the merchant-API.
        try {
            $seller_status = $this->get_seller_status_object();
            if ($this->check_api_response($seller_status)) {
                $this->mark_as_enabled();
            } else {
                $this->mark_as_disabled();
            }
        } catch (Exception $exception) {
            $this->has_request_failure = \true;
        }
        return (bool) $this->is_eligible;
    }
    /**
     * Instantly resets the local cache, so that the next call to `is_active()` triggers
     * a new API request to determine the feature availability.
     */
    public function clear(): void
    {
        $this->is_eligible = null;
        $this->has_request_failure = \false;
        $this->result_cache->clear(static::KEY);
    }
    /**
     * Inspects the API response of the SellerStatus to determine feature eligibility.
     *
     * Returns true when the feature is available, and false if ineligible.
     * On failure, an RuntimeException is thrown.
     *
     * @param SellerStatus $seller_status The seller status, returned from the API.
     * @return bool
     * @throws RuntimeException When the check failed.
     */
    abstract protected function check_api_response(SellerStatus $seller_status): bool;
    /**
     * Can be overwritten by child classes to filter the local state.
     *
     * This check is used to determine the `is_active()` state of the product.
     */
    public function check_local_state(bool $skip_filters = \false): ?bool
    {
        $local_state = $this->result_cache->get(static::KEY);
        if ($local_state) {
            return wc_string_to_bool($local_state);
        }
        return null;
    }
    protected function mark_as_enabled(): void
    {
        $this->is_eligible = \true;
        $this->result_cache->set(static::KEY, self::STATE_IS_ENABLED, $this->get_cache_lifespan(\true));
    }
    protected function mark_as_disabled(): void
    {
        $this->is_eligible = \false;
        $this->result_cache->set(static::KEY, self::STATE_IS_DISABLED, $this->get_cache_lifespan(\false));
    }
    /**
     * Defines the result-cache lifespan, in seconds. By default, the result does not expire,
     * but child classes can override this to define custom TTLs.
     */
    protected function get_cache_lifespan(bool $is_eligible): int
    {
        return 0;
    }
    /**
     * Fetches the seller-status object from the PayPal merchant API.
     *
     * @return SellerStatus
     * @throws RuntimeException When the check failed.
     */
    protected function get_seller_status_object(): SellerStatus
    {
        if (null === self::$seller_status) {
            // Check API failure registry to prevent multiple failed API requests.
            if ($this->api_failure_registry->has_failure_in_timeframe(\WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry::SELLER_STATUS_KEY, MINUTE_IN_SECONDS)) {
                throw new RuntimeException('Timeout for re-check not reached yet');
            }
            // Request seller status via PayPal API, might throw an Exception.
            self::$seller_status = $this->partners_endpoint->seller_status();
        }
        return self::$seller_status;
    }
    /**
     * Whether the merchant was fully onboarded, and we have valid API credentials.
     *
     * @return bool True, if we can use the merchant API endpoints.
     */
    public function is_onboarded(): bool
    {
        return $this->is_connected;
    }
    /**
     * Returns if there was a request failure.
     *
     * @return bool
     */
    public function has_request_failure(): bool
    {
        return $this->has_request_failure;
    }
}
