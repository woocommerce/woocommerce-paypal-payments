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
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class ProductStatus
 *
 * Base class to check the eligibility of a product for the current merchant.
 */
abstract class ProductStatus
{
    /**
     * Caches the SellerStatus API response to avoid duplicate API calls
     * during the same request.
     *
     * @var ?SellerStatus
     */
    private static ?SellerStatus $seller_status = null;
    /**
     * The current status stored in memory.
     *
     * @var bool|null
     */
    private ?bool $is_eligible = null;
    /**
     * If there was a request failure.
     *
     * @var bool
     */
    private bool $has_request_failure = \false;
    /**
     * Whether the merchant onboarding process was completed and the
     * merchant API is available.
     *
     * @var bool
     */
    private bool $is_connected;
    /**
     * The partners endpoint.
     *
     * @var PartnersEndpoint
     */
    private PartnersEndpoint $partners_endpoint;
    /**
     * The API failure registry
     *
     * @var FailureRegistry
     */
    private \WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry $api_failure_registry;
    /**
     * AppleProductStatus constructor.
     *
     * @param bool             $is_connected         Whether the merchant is connected.
     * @param PartnersEndpoint $partners_endpoint    The Partner Endpoint.
     * @param FailureRegistry  $api_failure_registry The API failure registry.
     */
    public function __construct(bool $is_connected, PartnersEndpoint $partners_endpoint, \WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry $api_failure_registry)
    {
        $this->is_connected = $is_connected;
        $this->partners_endpoint = $partners_endpoint;
        $this->api_failure_registry = $api_failure_registry;
    }
    /**
     * Uses local data (DB values, hooks) to determine if the feature is eligible.
     *
     * Returns true when the feature is available, and false if ineligible.
     * On failure, an RuntimeException is thrown.
     *
     * @return null|bool Boolean to indicate the status; null if the status not locally defined.
     * @throws RuntimeException When the check failed.
     * @throws NotFoundException When a relevant service or setting was not found.
     */
    abstract protected function check_local_state(): ?bool;
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
    abstract protected function check_active_state(SellerStatus $seller_status): bool;
    /**
     * Clears the eligibility status from the local cache/DB to enforce a new
     * API call on the next eligibility check.
     *
     * @param Settings|null $settings See description in {@see self::clear()}.
     * @return void
     */
    abstract protected function clear_state(?Settings $settings = null): void;
    /**
     * Whether the merchant has access to the feature.
     *
     * @return bool
     */
    public function is_active(): bool
    {
        if (null !== $this->is_eligible) {
            return $this->is_eligible;
        }
        $this->is_eligible = \false;
        $this->has_request_failure = \false;
        if (!$this->is_onboarded()) {
            return $this->is_eligible;
        }
        try {
            // Try to use filters and DB values to determine the state.
            $local_state = $this->check_local_state();
            if (null !== $local_state) {
                $this->is_eligible = $local_state;
                return $this->is_eligible;
            }
            // Check using the merchant-API.
            $seller_status = $this->get_seller_status_object();
            $this->is_eligible = $this->check_active_state($seller_status);
        } catch (Exception $exception) {
            $this->has_request_failure = \true;
        }
        return $this->is_eligible;
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
    /**
     * Clears the persisted result to force a recheck.
     *
     * Accepts a Settings object to don't override other sequential settings that are being updated
     * elsewhere.
     *
     * @param Settings|null $settings The settings object.
     * @return void
     */
    public function clear(?Settings $settings = null): void
    {
        $this->is_eligible = null;
        $this->has_request_failure = \false;
        $this->clear_state($settings);
    }
}
