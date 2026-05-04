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
 *
 * All seven conditions must be true for the banner to be eligible.
 */
class AgenticBetaBannerEligibility
{
    private const REQUIRED_PRODUCT_COUNT = 1;
    private const REQUIRED_ORDER_COUNT = 1;
    private const ORDER_LOOKBACK_DAYS = 900;
    private GeneralSettings $general_settings;
    private string $store_country;
    public function __construct(GeneralSettings $general_settings, string $store_country)
    {
        $this->general_settings = $general_settings;
        $this->store_country = $store_country;
    }
    /**
     * Returns true only when all seven conditions are met:
     * merchant is connected, store country is US, a US shipping zone exists,
     * at least {@see self::REQUIRED_PRODUCT_COUNT} published products exist,
     * at least {@see self::REQUIRED_ORDER_COUNT} completed orders within the last
     * {@see self::ORDER_LOOKBACK_DAYS} days, the banner is not snoozed,
     * and the banner has not been permanently dismissed.
     *
     * @return bool
     */
    public function is_eligible(): bool
    {
        return $this->general_settings->is_merchant_connected() && $this->store_country === 'US' && $this->has_us_shipping_zone() && $this->has_enough_products() && $this->has_enough_recent_orders() && $this->is_not_snoozed() && !get_option(AgenticBetaBannerEndpoint::OPTION_DISMISSED);
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
            foreach ($zone->get_zone_locations() as $location) {
                if ($location->type === 'country' && $location->code === 'US') {
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
