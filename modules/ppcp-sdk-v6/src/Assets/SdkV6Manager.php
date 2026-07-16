<?php

/**
 * Manages the SDK v6 frontend assets.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
/**
 * Class SdkV6Manager
 */
class SdkV6Manager
{
    /**
     * The asset getter.
     *
     * @var AssetGetter
     */
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private string $version;
    /**
     * The environment object.
     *
     * @var Environment
     */
    private Environment $environment;
    /**
     * SdkV6Manager constructor.
     *
     * @param AssetGetter $asset_getter The asset getter.
     * @param string      $version The assets version.
     * @param Environment $environment The environment object.
     */
    public function __construct(AssetGetter $asset_getter, string $version, Environment $environment)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->environment = $environment;
    }
    /**
     * Enqueues scripts/styles.
     *
     * @return void
     */
    public function enqueue(): void
    {
        if (!$this->should_enqueue()) {
            return;
        }
        wp_register_script('wc-ppcp-sdk-v6-boot', $this->asset_getter->get_asset_url('boot.js'), array(), $this->version, \true);
        wp_localize_script('wc-ppcp-sdk-v6-boot', 'wc_ppcp_sdk_v6', $this->script_data());
        wp_enqueue_script('wc-ppcp-sdk-v6-boot');
    }
    /**
     * Whether the scripts should be enqueued on the current page.
     *
     * @return bool
     */
    private function should_enqueue(): bool
    {
        return is_checkout() || is_cart();
    }
    /**
     * The configuration data for the SDK v6 bootstrap script.
     *
     * @return array
     */
    private function script_data(): array
    {
        $base_url = $this->environment->is_sandbox() ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
        return array('sdk_url' => $base_url . '/web-sdk/v6/core', 'ajax' => array('client_token' => array('endpoint' => \WC_AJAX::get_endpoint(ClientTokenEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(ClientTokenEndpoint::nonce()))));
    }
}
