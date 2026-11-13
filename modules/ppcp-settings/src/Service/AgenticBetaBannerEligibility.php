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
class AgenticBetaBannerEligibility
{
    private const REQUIRED_PRODUCT_COUNT = 50;
    private const REQUIRED_ORDER_COUNT = 50;
    private const ORDER_LOOKBACK_DAYS = 90;
    private const TRANSIENT_KEY = 'ppcp_agentic_banner_base_eligible';
    private const ELIGIBLE_COUNTRIES = array('US');
    private GeneralSettings $general_settings;
    private string $store_country;
    public function __construct(GeneralSettings $general_settings, string $store_country)
    {
        $this->general_settings = $general_settings;
        $this->store_country = $store_country;
    }
    public function is_eligible(): bool
    {
        // Cheap option reads first, to skip the store-level DB queries.
        if ($this->is_dismissed() || $this->is_snoozed()) {
            return \false;
        }
        return $this->are_store_conditions_met();
    }
    private function is_dismissed(): bool
    {
        return (bool) get_option(AgenticBetaBannerEndpoint::OPTION_DISMISSED);
    }
    private function is_snoozed(): bool
    {
        $snoozed_until = get_option(AgenticBetaBannerEndpoint::OPTION_SNOOZED_UNTIL);
        return $snoozed_until && (int) $snoozed_until >= time();
    }
    private function are_store_conditions_met(): bool
    {
        if (!version_compare(\PHP_VERSION, '8.1', '>=')) {
            return \false;
        }
        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== \false) {
            return (bool) $cached;
        }
        $result = $this->general_settings->is_merchant_connected() && $this->is_eligible_country($this->general_settings->get_merchant_data()->merchant_country) && $this->is_eligible_country($this->store_country) && $this->has_eligible_shipping_zone() && $this->has_enough_products() && $this->has_enough_recent_orders();
        set_transient(self::TRANSIENT_KEY, (int) $result, DAY_IN_SECONDS);
        return $result;
    }
    private function is_eligible_country(string $country): bool
    {
        return in_array($country, self::ELIGIBLE_COUNTRIES, \true);
    }
    private function has_eligible_shipping_zone(): bool
    {
        $rest_of_world_zone = new \WC_Shipping_Zone(0);
        if (!empty($rest_of_world_zone->get_shipping_methods(\true))) {
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
                if ($this->is_eligible_location($location)) {
                    return \true;
                }
            }
        }
        return \false;
    }
    private function is_eligible_location(\stdClass $location): bool
    {
        foreach (self::ELIGIBLE_COUNTRIES as $country) {
            $continent = WC()->countries->get_continent_code_for_country($country);
            if ($location->type === 'continent' && $location->code === $continent || $location->type === 'country' && $location->code === $country || $location->type === 'state' && strpos($location->code, $country . ':') === 0) {
                return \true;
            }
        }
        return \false;
    }
    private function has_enough_products(): bool
    {
        $products = wc_get_products(array('status' => 'publish', 'limit' => self::REQUIRED_PRODUCT_COUNT, 'return' => 'ids'));
        return is_array($products) && count($products) >= self::REQUIRED_PRODUCT_COUNT;
    }
    private function has_enough_recent_orders(): bool
    {
        $orders = wc_get_orders(array('status' => 'wc-completed', 'date_created' => '>' . gmdate('Y-m-d', strtotime('-' . self::ORDER_LOOKBACK_DAYS . ' days')), 'limit' => self::REQUIRED_ORDER_COUNT, 'return' => 'ids'));
        return is_array($orders) && count($orders) >= self::REQUIRED_ORDER_COUNT;
    }
}
