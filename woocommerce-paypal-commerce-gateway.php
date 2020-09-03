<?php # -*- coding: utf-8 -*-
declare( strict_types = 1 );

/**
 * Plugin Name: PayPal for Woocommerce
 * Plugin URI:  TODO
 * Description: PayPal's latest complete payments processing solution. Accept PayPal. PayPal Credit, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.
 * Version:     dev-master
 * Author:      Inpsyde GmbH
 * Author URI:  https://inpsyde.com/
 * License:     GPL-2.0
 * Text Domain: paypal-for-woocommerce
 */


namespace Inpsyde\PayPalCommerce;

use Dhii\Container\CachingContainer;
use Dhii\Container\CompositeCachingServiceProvider;
use Dhii\Container\DelegatingContainer;
use Dhii\Container\ProxyContainer;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

(function () {
	include __DIR__ . '/vendor/autoload.php';

    function init()
    {
        static $initialized;
        if (!$initialized) {
            $modules = [new PluginModule()];
            foreach (glob(plugin_dir_path(__FILE__).'modules/*/module.php') as $moduleFile) {
                $modules[] = (@require $moduleFile)();
            }
            $providers = [];
            foreach ($modules as $module) {
                /* @var $module ModuleInterface */
                $providers[] = $module->setup();
            }
            $proxy = new ProxyContainer();
            $provider = new CompositeCachingServiceProvider($providers);
            $container = new CachingContainer(new DelegatingContainer($provider));
            $proxy->setInnerContainer($container);
            foreach ($modules as $module) {
                /* @var $module ModuleInterface */
                $module->run($container);
            }
            $initialized = true;

        }
    }

    add_action(
        'plugins_loaded',
        function () {
            init();
        }
    );
    register_activation_hook(
        __FILE__,
        function () {
            init();
            do_action('woocommerce-paypal-commerce-gateway.activate');
        }
    );
    register_deactivation_hook(
        __FILE__,
        function () {
            init();
            do_action('woocommerce-paypal-commerce-gateway.deactivate');
        }
    );

})();