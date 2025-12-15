<?php

/**
 * The order tracking module services.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking;

use WooCommerce\PayPalCommerce\OrderTracking\Integration\DhlShipmentIntegration;
use WooCommerce\PayPalCommerce\OrderTracking\Integration\GermanizedShipmentIntegration;
use WooCommerce\PayPalCommerce\OrderTracking\Integration\ShipmentTrackingIntegration;
use WooCommerce\PayPalCommerce\OrderTracking\Integration\ShipStationIntegration;
use WooCommerce\PayPalCommerce\OrderTracking\Integration\WcShippingTaxIntegration;
use WooCommerce\PayPalCommerce\OrderTracking\Integration\YithShipmentIntegration;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactory;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\OrderTracking\Assets\OrderEditPageAssets;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
return array(
    'order-tracking.assets' => function (ContainerInterface $container): OrderEditPageAssets {
        return new OrderEditPageAssets($container->get('order-tracking.module.url'), $container->get('ppcp.asset-version'));
    },
    'order-tracking.shipment.factory' => static function (ContainerInterface $container): ShipmentFactoryInterface {
        return new ShipmentFactory();
    },
    'order-tracking.endpoint.controller' => static function (ContainerInterface $container): OrderTrackingEndpoint {
        return new OrderTrackingEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'), $container->get('button.request-data'), $container->get('order-tracking.shipment.factory'), $container->get('order-tracking.allowed-shipping-statuses'), $container->get('order-tracking.should-use-second-version-of-api'));
    },
    'order-tracking.module.url' => static function (ContainerInterface $container): string {
        return plugins_url('/modules/ppcp-order-tracking/', $container->get('ppcp.path-to-plugin-main-file'));
    },
    'order-tracking.meta-box.renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\OrderTracking\MetaBoxRenderer {
        return new \WooCommerce\PayPalCommerce\OrderTracking\MetaBoxRenderer($container->get('order-tracking.allowed-shipping-statuses'), $container->get('order-tracking.available-carriers'), $container->get('order-tracking.endpoint.controller'), $container->get('order-tracking.should-use-second-version-of-api'), $container->get('woocommerce.logger.woocommerce'));
    },
    'order-tracking.allowed-shipping-statuses' => static function (ContainerInterface $container): array {
        return (array) apply_filters('woocommerce_paypal_payments_tracking_statuses', array('SHIPPED' => 'Shipped', 'ON_HOLD' => 'On Hold', 'DELIVERED' => 'Delivered', 'CANCELLED' => 'Cancelled'));
    },
    'order-tracking.allowed-carriers' => static function (ContainerInterface $container): array {
        return require __DIR__ . '/carriers.php';
    },
    'order-tracking.available-carriers' => static function (ContainerInterface $container): array {
        $api_shop_country = $container->get('api.shop.country');
        $allowed_carriers = $container->get('order-tracking.allowed-carriers');
        $selected_country_carriers = $allowed_carriers[$api_shop_country] ?? array();
        return array($api_shop_country => $selected_country_carriers ?? array(), 'global' => $allowed_carriers['global'] ?? array(), 'other' => array('name' => 'Other', 'items' => array('OTHER' => _x('Other', 'Name of carrier', 'woocommerce-paypal-payments'))));
    },
    /**
     * The list of country codes, for which the 2nd version of PayPal tracking API is supported.
     */
    'order-tracking.second-version-api-supported-countries' => static function (): array {
        /**
         * Returns codes of countries, for which the 2nd version of PayPal tracking API is supported.
         */
        return apply_filters('woocommerce_paypal_payments_supported_country_codes_for_second_version_of_tracking_api', array('US', 'AU', 'CA', 'FR', 'DE', 'IT', 'ES', 'GB'));
    },
    'order-tracking.should-use-second-version-of-api' => static function (ContainerInterface $container): bool {
        $supported_county_codes = $container->get('order-tracking.second-version-api-supported-countries');
        $selected_country_code = $container->get('api.shop.country');
        return in_array($selected_country_code, $supported_county_codes, \true);
    },
    'order-tracking.integrations' => static function (ContainerInterface $container): array {
        $shipment_factory = $container->get('order-tracking.shipment.factory');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $endpoint = $container->get('order-tracking.endpoint.controller');
        $is_gzd_active = $container->get('compat.gzd.is_supported_plugin_version_active');
        $is_wc_shipment_active = $container->get('compat.wc_shipment_tracking.is_supported_plugin_version_active');
        $is_yith_ywot_active = $container->get('compat.ywot.is_supported_plugin_version_active');
        $is_dhl_de_active = $container->get('compat.dhl.is_supported_plugin_version_active');
        $is_ship_station_active = $container->get('compat.shipstation.is_supported_plugin_version_active');
        $is_wc_shipping_tax_active = $container->get('compat.wc_shipping_tax.is_supported_plugin_version_active');
        $integrations = array();
        if ($is_gzd_active) {
            $integrations[] = new GermanizedShipmentIntegration($shipment_factory, $logger, $endpoint);
        }
        if ($is_wc_shipment_active) {
            $integrations[] = new ShipmentTrackingIntegration($shipment_factory, $logger, $endpoint);
        }
        if ($is_yith_ywot_active) {
            $integrations[] = new YithShipmentIntegration($shipment_factory, $logger, $endpoint);
        }
        if ($is_dhl_de_active) {
            $integrations[] = new DhlShipmentIntegration($shipment_factory, $logger, $endpoint);
        }
        if ($is_ship_station_active) {
            $integrations[] = new ShipStationIntegration($shipment_factory, $logger, $endpoint);
        }
        if ($is_wc_shipping_tax_active) {
            $integrations[] = new WcShippingTaxIntegration($shipment_factory, $logger, $endpoint);
        }
        return $integrations;
    },
);
