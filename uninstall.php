<?php

/**
 * Uninstalls the plugin.
 *
 * @package WooCommerce\PayPalCommerce
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\Uninstall\ClearDatabaseInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\NotFoundExceptionInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
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
    try {
        $bootstrap = require "{$root_dir}/bootstrap.php";
        $app_container = $bootstrap($root_dir);
        assert($app_container instanceof ContainerInterface);
        clear_plugin_branding($app_container);
        $settings = $app_container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        // TODO: This is a flag only present in the #legacy-ui. Should we change this to a filter, or remove the DB reset code?
        $should_clear_db = $settings->has('uninstall_clear_db_on_uninstall') && $settings->get('uninstall_clear_db_on_uninstall');
        if (!$should_clear_db) {
            return;
        }
        $clear_db = $app_container->get('uninstall.clear-db');
        assert($clear_db instanceof ClearDatabaseInterface);
        $option_names = $app_container->get('uninstall.ppcp-all-option-names');
        $scheduled_action_names = $app_container->get('uninstall.ppcp-all-scheduled-action-names');
        $clear_db->delete_options($option_names);
        $clear_db->clear_scheduled_actions($scheduled_action_names);
    } catch (\WooCommerce\PayPalCommerce\Throwable $throwable) {
        $message = sprintf('<strong>Error:</strong> %s <br><pre>%s</pre>', $throwable->getMessage(), $throwable->getTraceAsString());
        add_action('all_admin_notices', static function () use ($message) {
            $class = 'notice notice-error';
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
    }
})($root_dir);
/**
 * Clears plugin branding by resetting the installation path flag.
 *
 * @param ContainerInterface $container The plugin's DI container.
 * @return void
 */
function clear_plugin_branding(ContainerInterface $container): void
{
    /*
     * This flag is set by WooCommerce when the plugin is installed via their
     * Settings page. We remove it here, as uninstalling the plugin should
     * open up the possibility of installing it from a different source in
     * "white label" mode.
     */
    delete_option('woocommerce_paypal_branded');
    delete_option('ppcp_bn_code');
    try {
        $general_settings = $container->get('settings.data.general');
        assert($general_settings instanceof GeneralSettings);
        if ($general_settings->reset_installation_path('plugin_uninstall')) {
            $general_settings->save();
        }
    } catch (NotFoundExceptionInterface $e) {
        // The container does not exist or did not return a GeneralSettings instance.
        // In any case: A failure can be ignored, as it means we cannot reset anything.
        return;
    }
}
