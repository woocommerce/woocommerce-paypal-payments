<?php

/**
 * Register and configure assets for order edit page.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking\Assets;

use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
/**
 * Class OrderEditPageAssets
 */
class OrderEditPageAssets
{
    /**
     * The URL to the module.
     *
     * @var string
     */
    private $module_url;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * WebhooksStatusPageAssets constructor.
     *
     * @param string $module_url                         The URL to the module.
     * @param string $version                            The assets version.
     */
    public function __construct(string $module_url, string $version)
    {
        $this->module_url = $module_url;
        $this->version = $version;
    }
    /**
     * Registers the scripts and styles.
     *
     * @return void
     */
    public function register(): void
    {
        wp_register_style('ppcp-webhooks-order-edit-page-style', untrailingslashit($this->module_url) . '/assets/css/order-edit-page.css', array(), $this->version);
        wp_register_script('ppcp-tracking', untrailingslashit($this->module_url) . '/assets/js/order-edit-page.js', array('jquery'), $this->version, \true);
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
