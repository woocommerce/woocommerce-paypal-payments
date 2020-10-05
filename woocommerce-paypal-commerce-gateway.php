<?php
/**
 * Plugin Name: PayPal Payments for WooCommerce
 * Plugin URI:  TODO
 * Description: PayPal's latest complete payments processing solution. Accept PayPal, PayPal Credit, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.
 * Version:     dev-master
 * Author:      WooCommerce
 * Author URI:  https://inpsyde.com/
 * License:     GPL-2.0
 * Text Domain: paypal-payments-for-woocommerce
 *
 * @package WooCommerce\PayPalCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce;

use Dhii\Container\CachingContainer;
use Dhii\Container\CompositeCachingServiceProvider;
use Dhii\Container\DelegatingContainer;
use Dhii\Container\ProxyContainer;
use Dhii\Modular\Module\ModuleInterface;

define( 'PAYPAL_API_URL', 'https://api.paypal.com' );
define( 'PAYPAL_SANDBOX_API_URL', 'https://api.sandbox.paypal.com' );

// @ToDo: Real connect.woocommerce.com production link.
define( 'CONNECT_WOO_URL', 'http://connect-woo.wpcust.com/ppc' );
define( 'CONNECT_WOO_SANDBOX_URL', 'http://connect-woo.wpcust.com/ppcsandbox' );

( function () {
	include __DIR__ . '/vendor/autoload.php';

	/**
	 * Initialize the plugin and its modules.
	 */
	function init() {
		static $initialized;
		if ( ! $initialized ) {
			$modules = array( new PluginModule() );
			foreach ( glob( plugin_dir_path( __FILE__ ) . 'modules/*/module.php' ) as $module_file ) {
				$modules[] = ( require $module_file )();
			}
			$providers = array();
			foreach ( $modules as $module ) {
				/* @var $module ModuleInterface module */
				$providers[] = $module->setup();
			}
			$proxy     = new ProxyContainer();
			$provider  = new CompositeCachingServiceProvider( $providers );
			$container = new CachingContainer( new DelegatingContainer( $provider ) );
			$proxy->setInnerContainer( $container );
			foreach ( $modules as $module ) {
				/* @var $module ModuleInterface module */
				$module->run( $container );
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
			do_action( 'woocommerce_paypal_commerce_gateway_activate' );
		}
	);
	register_deactivation_hook(
		__FILE__,
		function () {
			init();
			do_action( 'woocommerce_paypal_commerce_gateway_deactivate' );
		}
	);

} )();
