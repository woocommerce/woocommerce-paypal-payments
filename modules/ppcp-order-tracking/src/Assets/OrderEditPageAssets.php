<?php

/**
 * Register and configure assets for order edit page.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
/**
 * Class OrderEditPageAssets
 */
class OrderEditPageAssets
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * @param AssetGetter $asset_getter
     * @param string      $version                            The assets version.
     */
    public function __construct(AssetGetter $asset_getter, string $version)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
    }
    /**
     * Registers the scripts and styles.
     *
     * @return void
     */
    public function register(): void
    {
        wp_register_style('ppcp-webhooks-order-edit-page-style', $this->asset_getter->get_asset_url('order-edit-page.css'), array(), $this->version);
        wp_register_script('ppcp-tracking', $this->asset_getter->get_asset_url('order-edit-page.js'), array('jquery'), $this->version, \true);
        wp_localize_script('ppcp-tracking', 'PayPalCommerceGatewayOrderTrackingInfo', $this->get_script_data());
    }
    /**
     * Returns the data for the script.
     *
     * @return array a map of script data.
     */
    public function get_script_data(): array
    {
        return array('ajax' => array('tracking_info' => array('endpoint' => \WC_AJAX::get_endpoint(OrderTrackingEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(OrderTrackingEndpoint::nonce()), 'url' => admin_url('admin-ajax.php'))));
    }
    /**
     * Enqueues the necessary scripts.
     *
     * @return void
     */
    public function enqueue(): void
    {
        wp_enqueue_style('ppcp-webhooks-order-edit-page-style');
        wp_enqueue_script('ppcp-tracking');
    }
}
