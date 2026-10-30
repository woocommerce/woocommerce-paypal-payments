<?php

/**
 * Register and configure assets for Compat module.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\OrderTracking\TrackingAvailabilityTrait;
/**
 * Class OrderEditPageAssets
 */
class CompatAssets
{
    use TrackingAvailabilityTrait;
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * Whether Shiptastic plugin is active.
     *
     * @var bool
     */
    protected $is_shiptastic_active;
    /**
     * Whether WC Shipments plugin is active
     *
     * @var bool
     */
    protected $is_wc_shipment_active;
    /**
     * Whether WC Shipping & Tax plugin is active
     *
     * @var bool
     */
    private $is_wc_shipping_tax_active;
    /**
     * The bearer.
     *
     * @var Bearer
     */
    protected $bearer;
    /**
     * @param AssetGetter $asset_getter
     * @param string      $version The assets version.
     * @param bool        $is_shiptastic_active Whether Shiptastic plugin is active.
     * @param bool        $is_wc_shipment_active Whether WC Shipments plugin is active.
     * @param bool        $is_wc_shipping_tax_active Whether WC Shipping & Tax plugin is active.
     * @param Bearer      $bearer The bearer.
     */
    public function __construct(AssetGetter $asset_getter, string $version, bool $is_shiptastic_active, bool $is_wc_shipment_active, bool $is_wc_shipping_tax_active, Bearer $bearer)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->is_shiptastic_active = $is_shiptastic_active;
        $this->is_wc_shipment_active = $is_wc_shipment_active;
        $this->is_wc_shipping_tax_active = $is_wc_shipping_tax_active;
        $this->bearer = $bearer;
    }
    /**
     * Registers the scripts and styles.
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->is_tracking_enabled($this->bearer)) {
            wp_register_script('ppcp-tracking-compat', $this->asset_getter->get_asset_url('tracking-compat.js'), array('jquery'), $this->version, \true);
            wp_localize_script('ppcp-tracking-compat', 'PayPalCommerceGatewayOrderTrackingCompat', array('shiptastic_sync_enabled' => apply_filters('woocommerce_paypal_payments_sync_shiptastic_tracking', \true) && $this->is_shiptastic_active, 'wc_shipment_sync_enabled' => apply_filters('woocommerce_paypal_payments_sync_wc_shipment_tracking', \true) && $this->is_wc_shipment_active, 'wc_shipping_tax_sync_enabled' => apply_filters('woocommerce_paypal_payments_sync_wc_shipping_tax', \true) && $this->is_wc_shipping_tax_active));
        }
    }
    /**
     * Enqueues the necessary scripts.
     *
     * @return void
     */
    public function enqueue(): void
    {
        if ($this->is_tracking_enabled($this->bearer)) {
            wp_enqueue_script('ppcp-tracking-compat');
        }
    }
}
