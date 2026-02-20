<?php

/**
 * Uninstalls the plugin.
 *
 * @package WooCommerce\PayPalCommerce
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce;

use Throwable;
use WooCommerce\PayPalCommerce\Uninstall\ClearDatabase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die('Direct access not allowed.');
}
$root_dir = __DIR__;
$main_plugin_file = "{$root_dir}/woocommerce-paypal-payments.php";
if (!file_exists($main_plugin_file)) {
    return;
}
require $main_plugin_file;
(static function (string $root_dir): void {
    $autoload_filepath = "{$root_dir}/vendor/autoload.php";
    if (file_exists($autoload_filepath) && !class_exists('\WooCommerce\PayPalCommerce\PluginModule')) {
        require $autoload_filepath;
    }
    $bootstrap = require "{$root_dir}/bootstrap.php";
    $app_container = $bootstrap($root_dir);
    assert($app_container instanceof ContainerInterface);
    $general_settings = $app_container->get('settings.data.general');
    assert($general_settings instanceof GeneralSettings);
    if ($general_settings->reset_installation_path('plugin_uninstall')) {
        $general_settings->save();
    }
    /**
     * Allows a full reset of the plugin data.
     *
     * By default, this is false, preserving plugin settings in the DB during uninstallation.
     * This filter has no toggle in the UI yet and can only be set using custom code.
     */
    $should_reset_db = apply_filters('woocommerce_paypal_payments_uninstall_full_reset', \false);
    if ($should_reset_db) {
        $clear_db = $app_container->get('uninstall.clear-db');
        assert($clear_db instanceof ClearDatabase);
        $clear_db->clean_up();
    }
})($root_dir);
