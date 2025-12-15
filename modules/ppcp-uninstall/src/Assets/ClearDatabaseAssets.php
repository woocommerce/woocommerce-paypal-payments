<?php

/**
 * Register and configure assets for uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall\Assets;

/**
 * Class ClearDatabaseAssets
 */
class ClearDatabaseAssets
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
     * The script name.
     *
     * @var string
     */
    protected $script_name;
    /**
     * A map of script data.
     *
     * @var array
     */
    protected $script_data;
    /**
     * ClearDatabaseAssets constructor.
     *
     * @param string $module_url The URL to the module.
     * @param string $version The assets version.
     * @param string $script_name The script name.
     * @param array  $script_data A map of script data.
     */
    public function __construct(string $module_url, string $version, string $script_name, array $script_data)
    {
        $this->module_url = $module_url;
        $this->version = $version;
        $this->script_data = $script_data;
        $this->script_name = $script_name;
    }
    /**
     * Registers the scripts and styles.
     *
     * @return void
     */
    public function register(): void
    {
        $module_url = untrailingslashit($this->module_url);
        wp_register_script($this->script_name, "{$module_url}/assets/js/{$this->script_name}.js", array('jquery'), $this->version, \true);
        wp_localize_script($this->script_name, 'PayPalCommerceGatewayClearDb', $this->script_data);
    }
    /**
     * Enqueues the necessary scripts.
     *
     * @return void
     */
    public function enqueue(): void
    {
        wp_enqueue_script($this->script_name);
    }
}
