<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Assets;

class AssetGetterFactory
{
    protected string $base_plugin_url;
    protected string $plugin_folder_path;
    public function __construct(string $base_plugin_url, string $plugin_folder_path)
    {
        $this->base_plugin_url = $base_plugin_url;
        $this->plugin_folder_path = $plugin_folder_path;
    }
    public function for_module(string $module_name): \WooCommerce\PayPalCommerce\Assets\AssetGetter
    {
        return new \WooCommerce\PayPalCommerce\Assets\AssetGetter($this->base_plugin_url, $this->plugin_folder_path, $module_name);
    }
}
