<?php
/**
 * Plugin Name: WooCommerce PayPal Payments
 * Plugin URI:  https://woocommerce.com/products/woocommerce-paypal-payments/
 * Description: PayPal's latest complete payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.
 * Version:     1.6.1
 * Author:      WooCommerce
 * Author URI:  https://woocommerce.com/
 * License:     GPL-2.0
 * Requires PHP: 7.1
 * WC requires at least: 3.9
 * WC tested up to: 5.7
 * Text Domain: woocommerce-paypal-payments
 *
 * @package WooCommerce\PayPalCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce;

define( 'PAYPAL_API_URL', 'https://api.paypal.com' );
define( 'PAYPAL_SANDBOX_API_URL', 'https://api.sandbox.paypal.com' );
define( 'PAYPAL_INTEGRATION_DATE', '2021-09-17' );

define( 'PPCP_FLAG_SUBSCRIPTION', true );

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
		$root_dir = __DIR__;

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			add_action(
				'admin_notices',
				function() {
					/* translators: 1. URL link. */
					echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'WooCommerce PayPal Payments requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-paypal-payments' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
				}
			);

			return;
		}
		if ( version_compare( PHP_VERSION, '7.1', '<' ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="error"><p>' . esc_html__( 'WooCommerce PayPal Payments requires PHP 7.1 or above.', 'woocommerce-paypal-payments' ), '</p></div>';
				}
			);

			return;
		}

		static $initialized;
		if ( ! $initialized ) {
			$bootstrap = require "$root_dir/bootstrap.php";

			$app_container = $bootstrap( $root_dir );

			$initialized = true;
			do_action( 'woocommerce_paypal_payments_built_container', $app_container );
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
			flush_rewrite_rules();
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
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}
			if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				return $links;
			}

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
