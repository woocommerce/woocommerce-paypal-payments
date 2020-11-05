<?php
/**
 * Plugin Name: WooCommerce PayPal Payments
 * Plugin URI:  https://woocommerce.com/products/woocommerce-paypal-payments/
 * Description: PayPal's latest complete payments processing solution. Accept PayPal, PayPal Credit, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.
 * Version:     1.0.1
 * Author:      WooCommerce
 * Author URI:  https://woocommerce.com/
 * License:     GPL-2.0
 * Text Domain: woocommerce-paypal-payments
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
define( 'PAYPAL_INTEGRATION_DATE', '2020-10-15' );

! defined( 'CONNECT_WOO_CLIENT_ID' ) && define( 'CONNECT_WOO_CLIENT_ID', 'AcCAsWta_JTL__OfpjspNyH7c1GGHH332fLwonA5CwX4Y10mhybRZmHLA0GdRbwKwjQIhpDQy0pluX_P' );
! defined( 'CONNECT_WOO_SANDBOX_CLIENT_ID' ) && define( 'CONNECT_WOO_SANDBOX_CLIENT_ID', 'AYmOHbt1VHg-OZ_oihPdzKEVbU3qg0qXonBcAztuzniQRaKE0w1Hr762cSFwd4n8wxOl-TCWohEa0XM_' );
! defined( 'CONNECT_WOO_MERCHANT_ID' ) && define( 'CONNECT_WOO_MERCHANT_ID', 'K8SKZ36LQBWXJ' );
! defined( 'CONNECT_WOO_SANDBOX_MERCHANT_ID' ) && define( 'CONNECT_WOO_SANDBOX_MERCHANT_ID', 'MPMFHQTVMBZ6G' );
! defined( 'CONNECT_WOO_URL' ) && define( 'CONNECT_WOO_URL', 'https://connect.woocommerce.com/ppc' );
! defined( 'CONNECT_WOO_SANDBOX_URL' ) && define( 'CONNECT_WOO_SANDBOX_URL', 'https://connect.woocommerce.com/ppcsandbox' );

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
			do_action( 'woocommerce_paypal_payments_gateway_activate' );
		}
	);
	register_deactivation_hook(
		__FILE__,
		function () {
			init();
			do_action( 'woocommerce_paypal_payments_gateway_deactivate' );
		}
	);

	// Add "Settings" link to Plugins screen.
	add_filter(
		'plugin_action_links_' . plugin_basename( __FILE__ ),
		function( $links ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%1$s">%2$s</a>',
					admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ),
					__( 'Settings', 'woocommerce-paypal-payments' )
				)
			);

			return $links;
		}
	);

} )();
