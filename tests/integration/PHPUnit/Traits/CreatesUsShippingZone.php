<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Traits;

use WC_Cache_Helper;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

/**
 * Provides a self-contained US shipping zone with a flat rate method, so tests
 * do not depend on shipping zones that exist in the site database.
 */
trait CreatesUsShippingZone
{
    private ?int $us_zone_id = null;

    private ?int $us_zone_instance_id = null;

    /**
     * @var callable|null
     */
    private $us_zone_criteria_filter = null;

    protected function create_us_shipping_zone(string $cost = '5.00'): void
    {
        $zone = new WC_Shipping_Zone();
        $zone->set_zone_name('PCP Test US Zone ' . uniqid());
        $zone->set_zone_order(0);
        $zone->save();
        $zone->set_locations([['code' => 'US', 'type' => 'country']]);
        $zone->save();

        $instance_id = $zone->add_shipping_method('flat_rate');

        update_option(
            'woocommerce_flat_rate_' . $instance_id . '_settings',
            [
                'title' => 'Flat rate',
                'tax_status' => 'none',
                'cost' => $cost,
            ]
        );

        $this->us_zone_id = $zone->get_id();
        $this->us_zone_instance_id = $instance_id;

        // Zones that already exist in the database must not match before this zone.
        $zone_id = $this->us_zone_id;
        $this->us_zone_criteria_filter = static function (array $criteria) use ($zone_id): array {
            $criteria[] = 'AND zones.zone_id = ' . $zone_id;
            return $criteria;
        };
        add_filter('woocommerce_get_zone_criteria', $this->us_zone_criteria_filter);

        WC_Cache_Helper::invalidate_cache_group('shipping_zones');
        WC_Cache_Helper::get_transient_version('shipping', true);
    }

    protected function remove_us_shipping_zone(): void
    {
        if ($this->us_zone_criteria_filter) {
            remove_filter('woocommerce_get_zone_criteria', $this->us_zone_criteria_filter);
            $this->us_zone_criteria_filter = null;
        }

        if (null !== $this->us_zone_id) {
            delete_option('woocommerce_flat_rate_' . $this->us_zone_instance_id . '_settings');
            WC_Shipping_Zones::delete_zone($this->us_zone_id);
            $this->us_zone_id = null;
            $this->us_zone_instance_id = null;
        }

        WC_Cache_Helper::invalidate_cache_group('shipping_zones');
        WC_Cache_Helper::get_transient_version('shipping', true);
    }
}
