<?php

/**
 * Plugin Name: WooCommerce PayPal Payments
 * Plugin URI:  https://woocommerce.com/products/woocommerce-paypal-payments/
 * Description: PayPal's latest complete payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.
 * Version: 3.4.0
 * Author:      PayPal
 * Author URI:  https://paypal.com/
 * License:     GPL-2.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Requires at least: 6.5
 * WC requires at least: 9.6
 * WC tested up to: 10.5
 * Text Domain: woocommerce-paypal-payments
 *
 * @package WooCommerce\PayPalCommerce
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
define('PAYPAL_API_URL', 'https://api-m.paypal.com');
define('PAYPAL_URL', 'https://www.paypal.com');
define('PAYPAL_SANDBOX_API_URL', 'https://api-m.sandbox.paypal.com');
define('PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com');
define('PAYPAL_INTEGRATION_DATE', '2026-02-05');
define('PPCP_PAYPAL_BN_CODE', 'Woo_PPCP');
!defined('CONNECT_WOO_CLIENT_ID') && define('CONNECT_WOO_CLIENT_ID', 'AcCAsWta_JTL__OfpjspNyH7c1GGHH332fLwonA5CwX4Y10mhybRZmHLA0GdRbwKwjQIhpDQy0pluX_P');
!defined('CONNECT_WOO_SANDBOX_CLIENT_ID') && define('CONNECT_WOO_SANDBOX_CLIENT_ID', 'AYmOHbt1VHg-OZ_oihPdzKEVbU3qg0qXonBcAztuzniQRaKE0w1Hr762cSFwd4n8wxOl-TCWohEa0XM_');
!defined('CONNECT_WOO_MERCHANT_ID') && define('CONNECT_WOO_MERCHANT_ID', 'K8SKZ36LQBWXJ');
!defined('CONNECT_WOO_SANDBOX_MERCHANT_ID') && define('CONNECT_WOO_SANDBOX_MERCHANT_ID', 'MPMFHQTVMBZ6G');
!defined('CONNECT_WOO_URL') && define('CONNECT_WOO_URL', 'https://api.woocommerce.com/integrations/ppc');
!defined('CONNECT_WOO_SANDBOX_URL') && define('CONNECT_WOO_SANDBOX_URL', 'https://api.woocommerce.com/integrations/ppcsandbox');
(function () {
    $autoload_filepath = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload_filepath) && !class_exists('\WooCommerce\PayPalCommerce\PluginModule')) {
        require $autoload_filepath;
    }
    /**
     * Displays an admin notice and optionally deactivates the current plugin.
     *
     * This function registers a callback to display administrative notices on both
     * single-site and network admin areas. It's typically used to show error messages
     * when plugin requirements are not met, followed by automatic plugin deactivation.
     *
     * @param callable $notice_callback The callback function that outputs the admin notice HTML.
     *                                  Should echo/print the notice markup directly.
     * @param bool     $auto_deactivate Optional. Whether to automatically deactivate the plugin
     *                                  after displaying the notice. Default true.
     *
     * @return void
     */
    function show_admin_notice_and_deactivate(callable $notice_callback, bool $auto_deactivate = \true): void
    {
        if (!is_callable($notice_callback)) {
            return;
        }
        $admin_notice_hooks = array('admin_notices', 'network_admin_notices');
        foreach ($admin_notice_hooks as $hook) {
            add_action($hook, static function () use ($notice_callback, $auto_deactivate) {
                $notice_callback();
                if ($auto_deactivate) {
                    deactivate_plugins(plugin_basename(__FILE__));
                    unset($_GET['activate']);
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                }
            });
        }
    }
    /**
     * Initialize the plugin and its modules.
     */
    function init(): void
    {
        $root_dir = __DIR__;
        if (!is_woocommerce_activated()) {
            show_admin_notice_and_deactivate(static fn() => printf('<div class="notice notice-error"><span class="notice-title">%1$s</span><p>%2$s</p></div>', esc_html__('The plugin WooCommerce PayPal Payments has been deactivated', 'woocommerce-paypal-payments'), wp_kses(sprintf(
                // translators: %s is a link to install WooCommerce.
                esc_html__('WooCommerce PayPal Payments requires WooCommerce to be installed and active. %s', 'woocommerce-paypal-payments'),
                sprintf('<a href="%s">%s</a>', esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce')), esc_html__('You can download WooCommerce here.', 'woocommerce-paypal-payments'))
            ), array('a' => array('href' => array(), 'target' => array())))));
            return;
        }
        if (version_compare(\PHP_VERSION, '7.4', '<')) {
            show_admin_notice_and_deactivate(static fn() => printf('<div class="notice notice-error"><span class="notice-title">%1$s</span><p>%2$s</p></div>', esc_html__('The plugin WooCommerce PayPal Payments has been deactivated', 'woocommerce-paypal-payments'), esc_html__('WooCommerce PayPal Payments requires PHP 7.4 or above.', 'woocommerce-paypal-payments')));
            return;
        }
        static $initialized;
        if (!$initialized) {
            $bootstrap = require "{$root_dir}/bootstrap.php";
            $app_container = $bootstrap($root_dir);
            \WooCommerce\PayPalCommerce\PPCP::init($app_container);
            $initialized = \true;
            /**
             * The hook fired after the plugin bootstrap with the app services container as parameter.
             */
            do_action('woocommerce_paypal_payments_built_container', $app_container);
        }
    }
    add_action('plugins_loaded', function () {
        init();
        if (!is_woocommerce_activated()) {
            return;
        }
        add_action('init', function () {
            $current_plugin_version = \WooCommerce\PayPalCommerce\PPCP::container()->get('ppcp.plugin-version');
            $installed_plugin_version = get_option('woocommerce-ppcp-version');
            if ($installed_plugin_version !== $current_plugin_version) {
                update_option('woocommerce-ppcp-version', $current_plugin_version);
                /**
                 * The hook fired when the plugin is installed or updated.
                 */
                do_action('woocommerce_paypal_payments_gateway_migrate', $installed_plugin_version);
                if ($installed_plugin_version) {
                    /**
                     * The hook fired when the plugin is updated.
                     */
                    do_action('woocommerce_paypal_payments_gateway_migrate_on_update');
                }
            }
        }, -1);
    });
    register_activation_hook(__FILE__, function () {
        init();
        /**
         * The hook fired in register_activation_hook.
         */
        do_action('woocommerce_paypal_payments_gateway_activate');
    });
    register_deactivation_hook(__FILE__, function () {
        init();
        /**
         * The hook fired in register_deactivation_hook.
         */
        do_action('woocommerce_paypal_payments_gateway_deactivate');
    });
    add_filter(
        'plugin_action_links_' . plugin_basename(__FILE__),
        /**
         * Add "Settings" link to Plugins screen.
         *
         * @param array $links
         * @return array
         */
        function ($links) {
            if (!is_woocommerce_activated()) {
                return $links;
            }
            array_unshift($links, sprintf('<a href="%1$s">%2$s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID), __('Settings', 'woocommerce-paypal-payments')));
            return $links;
        }
    );
    add_filter(
        'plugin_row_meta',
        /**
         * Add links below the description on the Plugins page.
         *
         * @param array $links
         * @param string $file
         * @return array
         */
        function ($links, $file) {
            if (plugin_basename(__FILE__) !== $file) {
                return $links;
            }
            return array_merge($links, array(sprintf('<a target="_blank" href="%1$s">%2$s</a>', 'https://woocommerce.com/document/woocommerce-paypal-payments/', __('Documentation', 'woocommerce-paypal-payments')), sprintf('<a target="_blank" href="%1$s">%2$s</a>', 'https://woocommerce.com/document/woocommerce-paypal-payments/#get-help', __('Get help', 'woocommerce-paypal-payments')), sprintf('<a target="_blank" href="%1$s">%2$s</a>', 'https://woocommerce.com/feature-requests/woocommerce-paypal-payments/', __('Request a feature', 'woocommerce-paypal-payments')), sprintf('<a target="_blank" href="%1$s">%2$s</a>', 'https://github.com/woocommerce/woocommerce-paypal-payments/issues/new?assignees=&labels=type%3A+bug&template=bug_report.md', __('Submit a bug', 'woocommerce-paypal-payments'))));
        },
        10,
        2
    );
    add_action('before_woocommerce_init', function () {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            /**
             * Skip WC class check.
             *
             * @psalm-suppress UndefinedClass
             */
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, \true);
        }
    });
    /**
     * Check if WooCommerce is active.
     *
     * @return bool true if WooCommerce is active, otherwise false.
     */
    function is_woocommerce_activated(): bool
    {
        return class_exists('woocommerce');
    }
    add_action(
        'woocommerce_paypal_payments_gateway_migrate',
        /**
         * Set new merchant flag on plugin install.
         *
         * When installing the plugin for the first time, we direct the user to
         * the new UI without a data migration, and fully hide the #legacy-ui.
         *
         * @param string|false $version String with previous installed plugin version.
         *                              Boolean false on first installation on a new site.
         */
        static function ($version) {
            if (!$version) {
                update_option('woocommerce-ppcp-is-new-merchant', '1');
            }
        }
    );
})();
