<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Assets;

/**
 * Returns the URLs/paths for plugin assets.
 */
class AssetGetter
{
    protected string $base_plugin_url;
    protected string $plugin_folder_path;
    protected string $module_name;
    public function __construct(string $base_plugin_url, string $plugin_folder_path, string $module_name)
    {
        $this->base_plugin_url = $base_plugin_url;
        $this->plugin_folder_path = $plugin_folder_path;
        $this->module_name = $module_name;
    }
    /**
     * Returns URL for the compiled asset in the root assets/ dir.
     *
     * @param string $asset_name The asset name like 'index.js'.
     */
    public function get_asset_url(string $asset_name): string
    {
        $compiled_name = $this->get_compiled_asset_name($asset_name);
        return $this->base_plugin_url . 'assets/' . $compiled_name;
    }
    /**
     * Returns the path of the .asset.php file for the compiled asset in the root assets/ dir.
     *
     * @param string $asset_name The asset name like 'index.js'.
     */
    public function get_asset_php_path(string $asset_name): string
    {
        $compiled_name = $this->get_compiled_asset_name($asset_name);
        $without_ext = pathinfo($compiled_name, \PATHINFO_FILENAME);
        return trailingslashit($this->plugin_folder_path) . 'assets/' . "{$without_ext}.asset.php";
    }
    /**
     * Returns URL for the static asset (images, ...) in the module assets/ dir.
     *
     * @param string $asset_name The asset name like 'images/icon.svg'.
     */
    public function get_static_asset_url(string $asset_name): string
    {
        return $this->base_plugin_url . "modules/{$this->module_name}/assets/{$asset_name}";
    }
    protected function get_compiled_asset_name(string $asset_name): string
    {
        $type = pathinfo($asset_name, \PATHINFO_EXTENSION);
        $asset_name = str_replace('/', '-', $asset_name);
        return "{$this->module_name}-{$type}-{$asset_name}";
    }
}
