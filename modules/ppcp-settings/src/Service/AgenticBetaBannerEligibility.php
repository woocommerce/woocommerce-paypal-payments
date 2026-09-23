<?php

/**
 * Eligibility check for the agentic beta program banner.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Endpoint\AgenticBetaBannerEndpoint;
/**
 * Determines whether the agentic beta banner should be shown to the merchant.
 */
class AgenticBetaBannerEligibility
{
    private const REQUIRED_PRODUCT_COUNT = 50;
    private const REQUIRED_ORDER_COUNT = 50;
    private const ORDER_LOOKBACK_DAYS = 90;
    private const TRANSIENT_KEY = 'ppcp_agentic_banner_base_eligible';
    private GeneralSettings $general_settings;
    private string $store_country;
    public function __construct(GeneralSettings $general_settings, string $store_country)
    {
        $this->general_settings = $general_settings;
        $this->store_country = $store_country;
    }
    /**
     * Returns true only when all conditions are met. Checks user-preference
     * conditions first (cheap option reads) before falling through to the
     * store-level conditions, which are cached in a transient.
     *
     * @return bool
     */
    public function is_eligible(): bool
    {
        if (!$this->should_display_banner()) {
            return \false;
        }
        return $this->are_store_conditions_met();
    }
    /**
     * Returns false if the merchant has dismissed or snoozed the banner.
     *
     * @return bool
     */
    private function should_display_banner(): bool
    {
        return $this->is_not_snoozed() && !get_option(AgenticBetaBannerEndpoint::OPTION_DISMISSED);
    }
    /**
     * Returns true when the store-level conditions are met: PHP >= 8.1, merchant
     * connected, US store with a US shipping zone, and sufficient product/order
     * counts. The result is cached for 10 minutes to avoid repeated DB queries.
     *
     * @return bool
     */
    private function are_store_conditions_met(): bool
    {
        if (!version_compare(\PHP_VERSION, '8.1', '>=')) {
            return \false;
        }
        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== \false) {
            return (bool) $cached;
        }
        $result = $this->general_settings->is_merchant_connected() && $this->store_country === 'US' && $this->has_us_shipping_zone() && $this->has_enough_products() && $this->has_enough_recent_orders();
        set_transient(self::TRANSIENT_KEY, (int) $result, DAY_IN_SECONDS);
        return $result;
    }
    /**
     * Returns true if the default zone has active shipping methods, or if any
     * named zone covers the US country and has at least one active method.
     *
     * @return bool
     */
    private function has_us_shipping_zone(): bool
    {
        $default_zone = new \WC_Shipping_Zone(0);
        if (!empty($default_zone->get_shipping_methods(\true))) {
            return \true;
        }
        foreach (\WC_Shipping_Zones::get_zones() as $zone_data) {
            $zone = new \WC_Shipping_Zone($zone_data['id']);
            if (empty($zone->get_shipping_methods(\true))) {
                continue;
            }
            $locations = $zone->get_zone_locations();
            if (empty($locations)) {
                return \true;
            }
            foreach ($locations as $location) {
                if ($location->type === 'continent' && $location->code === 'NA' || $location->type === 'country' && $location->code === 'US' || $location->type === 'state' && strncmp($location->code, 'US:', 3) === 0) {
                    return \true;
                }
            }
        }
        return \false;
    }
    /**
     * Returns true if the store has at least {@see self::REQUIRED_PRODUCT_COUNT} published products.
     *
     * @return bool
     */
    private function has_enough_products(): bool
    {
        $products = wc_get_products(array('status' => 'publish', 'limit' => self::REQUIRED_PRODUCT_COUNT, 'return' => 'ids'));
        return is_array($products) && count($products) >= self::REQUIRED_PRODUCT_COUNT;
    }
    /**
     * Returns true if the store has at least {@see self::REQUIRED_ORDER_COUNT} completed orders
     * within the last {@see self::ORDER_LOOKBACK_DAYS} days.
     *
     * @return bool
     */
    private function has_enough_recent_orders(): bool
    {
        $orders = wc_get_orders(array('status' => 'wc-completed', 'date_created' => '>' . gmdate('Y-m-d', strtotime('-' . self::ORDER_LOOKBACK_DAYS . ' days')), 'limit' => self::REQUIRED_ORDER_COUNT, 'return' => 'ids'));
        return is_array($orders) && count($orders) >= self::REQUIRED_ORDER_COUNT;
    }
    /**
     * Returns true if no snooze is active or the snooze period has expired.
     *
     * @return bool
     */
    private function is_not_snoozed(): bool
    {
        $snoozed_until = get_option(AgenticBetaBannerEndpoint::OPTION_SNOOZED_UNTIL);
        return !$snoozed_until || (int) $snoozed_until < time();
    }
}
