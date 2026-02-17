<?php

/**
 * Base class for settings extension modules.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Extension;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
abstract class ExtensionSettingsModule
{
    /**
     * Relative path to the module's assets directory.
     *
     * Example: 'modules/ppcp-agentic-commerce/assets/'
     */
    protected const ASSETS_DIR = '';
    /**
     * Script handle for wp_enqueue_script.
     *
     * Example: 'ppcp-agentic-commerce-settings'
     */
    protected const SCRIPT_HANDLE = '';
    private string $absolute_plugin_path;
    private string $plugin_main_file;
    private \WooCommerce\PayPalCommerce\Settings\Extension\ExtensionRestEndpoint $settings_endpoint;
    private AssetGetter $asset_getter;
    public function __construct(string $absolute_plugin_path, string $plugin_main_file, \WooCommerce\PayPalCommerce\Settings\Extension\ExtensionRestEndpoint $settings_endpoint, AssetGetter $asset_getter)
    {
        $this->absolute_plugin_path = $absolute_plugin_path;
        $this->plugin_main_file = $plugin_main_file;
        $this->settings_endpoint = $settings_endpoint;
        $this->asset_getter = $asset_getter;
    }
    /**
     * Initializes the settings extension, must be called during the plugin_loaded action.
     *
     * Registers WordPress hooks for script enqueuing and REST endpoint registration.
     */
    public function init(): void
    {
        add_action('woocommerce_paypal_payments_settings_scripts_enqueued', fn() => $this->enqueue_settings_script());
        add_action('rest_api_init', fn() => $this->settings_endpoint->register_routes());
    }
    /**
     * Determines if the settings UI should be displayed.
     *
     * Called during script enqueuing, after the 'init' hook.
     * Override in child classes to conditionally hide settings based on decisions that
     * happen after plugin init is done.
     *
     * @return bool True to display settings, false to hide.
     */
    protected function is_available(): bool
    {
        return \true;
    }
    /**
     * Enqueues the settings JavaScript module - it must be named "settings.js"!
     */
    private function enqueue_settings_script(): void
    {
        if (!$this->is_available()) {
            return;
        }
        $assets_path = $this->asset_getter->get_asset_php_path('settings.js');
        $assets_url = $this->asset_getter->get_asset_url('settings.js');
        /** @psalm-suppress UnresolvableInclude - webpack generates this file */
        $script_asset_file = require $assets_path;
        wp_enqueue_script(static::SCRIPT_HANDLE, $assets_url . 'settings.js', $script_asset_file['dependencies'], $script_asset_file['version'], \true);
    }
}
