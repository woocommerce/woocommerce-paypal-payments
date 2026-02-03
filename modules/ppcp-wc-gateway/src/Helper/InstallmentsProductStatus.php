<?php

/**
 * Manage the Seller status for Installments.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
/**
 * Class InstallmentsProductStatus
 */
class InstallmentsProductStatus extends ProductStatus
{
    public const SETTINGS_KEY = 'products_installments_enabled';
    public const INSTALLMENTS_STATUS_CACHE_KEY = 'installments_status_cache';
    public const SETTINGS_VALUE_ENABLED = 'yes';
    public const SETTINGS_VALUE_DISABLED = 'no';
    public const SETTINGS_VALUE_UNDEFINED = '';
    /**
     * The Cache.
     *
     * @var Cache
     */
    protected Cache $cache;
    /**
     * The settings.
     *
     * @var Settings
     */
    private Settings $settings;
    /**
     * InstallmentsProductStatus constructor.
     *
     * @param Settings         $settings             The Settings.
     * @param PartnersEndpoint $partners_endpoint    The Partner Endpoint.
     * @param Cache            $cache                The cache.
     * @param bool             $is_connected         The onboarding state.
     * @param FailureRegistry  $api_failure_registry The API failure registry.
     */
    public function __construct(Settings $settings, PartnersEndpoint $partners_endpoint, Cache $cache, bool $is_connected, FailureRegistry $api_failure_registry)
    {
        parent::__construct($is_connected, $partners_endpoint, $api_failure_registry);
        $this->settings = $settings;
        $this->cache = $cache;
    }
    /** {@inheritDoc} */
    protected function check_local_state(): ?bool
    {
        if ($this->cache->has(self::INSTALLMENTS_STATUS_CACHE_KEY)) {
            return wc_string_to_bool($this->cache->get(self::INSTALLMENTS_STATUS_CACHE_KEY));
        }
        if ($this->settings->has(self::SETTINGS_KEY) && $this->settings->get(self::SETTINGS_KEY)) {
            return wc_string_to_bool($this->settings->get(self::SETTINGS_KEY));
        }
        return null;
    }
    /** {@inheritDoc} */
    protected function check_active_state(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->capabilities() as $capability) {
            if ($capability->name() !== 'INSTALLMENTS') {
                continue;
            }
            if ($capability->status() === 'ACTIVE') {
                $this->settings->set(self::SETTINGS_KEY, self::SETTINGS_VALUE_ENABLED);
                $this->settings->persist();
                $this->cache->set(self::INSTALLMENTS_STATUS_CACHE_KEY, self::SETTINGS_VALUE_ENABLED, MONTH_IN_SECONDS);
                return \true;
            }
        }
        $this->cache->set(self::INSTALLMENTS_STATUS_CACHE_KEY, self::SETTINGS_VALUE_DISABLED, MONTH_IN_SECONDS);
        return \false;
    }
    /** {@inheritDoc} */
    protected function clear_state(?Settings $settings = null): void
    {
        if (null === $settings) {
            $settings = $this->settings;
        }
        if ($settings->has(self::SETTINGS_KEY)) {
            $settings->set(self::SETTINGS_KEY, self::SETTINGS_VALUE_UNDEFINED);
            $settings->persist();
        }
        $this->cache->delete(self::INSTALLMENTS_STATUS_CACHE_KEY);
    }
}
