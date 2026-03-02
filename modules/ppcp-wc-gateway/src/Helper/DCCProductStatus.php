<?php

/**
 * Manage the Seller status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
/**
 * Class DccProductStatus
 */
class DCCProductStatus extends ProductStatus
{
    public const SETTINGS_KEY = 'products_dcc_enabled';
    public const DCC_STATUS_CACHE_KEY = 'dcc_status_cache';
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
     * The dcc applies helper.
     *
     * @var DccApplies
     */
    protected DccApplies $dcc_applies;
    /**
     * DccProductStatus constructor.
     *
     * @param Settings         $settings             The Settings.
     * @param PartnersEndpoint $partners_endpoint    The Partner Endpoint.
     * @param Cache            $cache                The cache.
     * @param DccApplies       $dcc_applies          The dcc applies helper.
     * @param bool             $is_connected         The onboarding state.
     * @param FailureRegistry  $api_failure_registry The API failure registry.
     */
    public function __construct(Settings $settings, PartnersEndpoint $partners_endpoint, Cache $cache, DccApplies $dcc_applies, bool $is_connected, FailureRegistry $api_failure_registry)
    {
        parent::__construct($is_connected, $partners_endpoint, $api_failure_registry);
        $this->settings = $settings;
        $this->cache = $cache;
        $this->dcc_applies = $dcc_applies;
    }
    /** {@inheritDoc} */
    protected function check_local_state(): ?bool
    {
        /**
         * Force BCDC (Standard Cards) for merchants migrated from legacy UI.
         *
         * This filter allows migrated merchants that used Standard Card buttons
         * in the legacy UI to maintain BCDC functionality in the new UI, regardless
         * of ACDC eligibility API responses.
         */
        $bcdc_override = apply_filters('woocommerce_paypal_payments_override_acdc_status_with_bcdc', null);
        if (\true === $bcdc_override) {
            // When overriding, short-circuit and mark ACDC as not available.
            return \false;
        }
        if ($this->cache->has(self::DCC_STATUS_CACHE_KEY)) {
            return wc_string_to_bool($this->cache->get(self::DCC_STATUS_CACHE_KEY));
        }
        if ($this->settings->has(self::SETTINGS_KEY) && $this->settings->get(self::SETTINGS_KEY)) {
            return wc_string_to_bool($this->settings->get(self::SETTINGS_KEY));
        }
        return null;
    }
    /** {@inheritDoc} */
    protected function check_active_state(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->products() as $product) {
            if (!in_array($product->vetting_status(), array(SellerStatusProduct::VETTING_STATUS_APPROVED, SellerStatusProduct::VETTING_STATUS_SUBSCRIBED), \true)) {
                continue;
            }
            // Settings used as a cache; `settings->set` is compatible with new UI.
            if (in_array('CUSTOM_CARD_PROCESSING', $product->capabilities(), \true)) {
                $this->settings->set(self::SETTINGS_KEY, self::SETTINGS_VALUE_ENABLED);
                $this->settings->persist();
                $this->cache->set(self::DCC_STATUS_CACHE_KEY, self::SETTINGS_VALUE_ENABLED, MONTH_IN_SECONDS);
                return \true;
            }
        }
        if ($this->dcc_applies->for_country_currency()) {
            $expiration = 3 * HOUR_IN_SECONDS;
        } else {
            $expiration = MONTH_IN_SECONDS;
        }
        $this->cache->set(self::DCC_STATUS_CACHE_KEY, self::SETTINGS_VALUE_DISABLED, $expiration);
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
        $this->cache->delete(self::DCC_STATUS_CACHE_KEY);
    }
}
