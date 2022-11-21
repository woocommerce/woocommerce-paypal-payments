<?php
/**
 * Plugin Name: WooCommerce PayPal Payments
 * Plugin URI:  https://woocommerce.com/products/woocommerce-paypal-payments/
 * Description: PayPal's latest complete payments processing solution. Accept PayPal, Pay Later, credit/debit cards, alternative digital wallets local payment types and bank accounts. Turn on only PayPal options or process a full suite of payment methods. Enable global transaction with extensive currency and country coverage.
 * Version:     2.0.0
 * Author:      WooCommerce
 * Author URI:  https://woocommerce.com/
 * License:     GPL-2.0
 * Requires PHP: 7.2
 * WC requires at least: 3.9
 * WC tested up to: 7.1
 * Text Domain: woocommerce-paypal-payments
 *
 * @package WooCommerce\PayPalCommerce
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

define( 'PAYPAL_API_URL', 'https://api.paypal.com' );
define( 'PAYPAL_SANDBOX_API_URL', 'https://api.sandbox.paypal.com' );
define( 'PAYPAL_INTEGRATION_DATE', '2022-04-13' );

define( 'PPCP_FLAG_SUBSCRIPTION', true );

! defined( 'CONNECT_WOO_CLIENT_ID' ) && define( 'CONNECT_WOO_CLIENT_ID', 'AcCAsWta_JTL__OfpjspNyH7c1GGHH332fLwonA5CwX4Y10mhybRZmHLA0GdRbwKwjQIhpDQy0pluX_P' );
! defined( 'CONNECT_WOO_SANDBOX_CLIENT_ID' ) && define( 'CONNECT_WOO_SANDBOX_CLIENT_ID', 'AYmOHbt1VHg-OZ_oihPdzKEVbU3qg0qXonBcAztuzniQRaKE0w1Hr762cSFwd4n8wxOl-TCWohEa0XM_' );
! defined( 'CONNECT_WOO_MERCHANT_ID' ) && define( 'CONNECT_WOO_MERCHANT_ID', 'K8SKZ36LQBWXJ' );
! defined( 'CONNECT_WOO_SANDBOX_MERCHANT_ID' ) && define( 'CONNECT_WOO_SANDBOX_MERCHANT_ID', 'MPMFHQTVMBZ6G' );
! defined( 'CONNECT_WOO_URL' ) && define( 'CONNECT_WOO_URL', 'https://connect.woocommerce.com/ppc' );
! defined( 'CONNECT_WOO_SANDBOX_URL' ) && define( 'CONNECT_WOO_SANDBOX_URL', 'https://connect.woocommerce.com/ppcsandbox' );

( function () {
	$autoload_filepath = __DIR__ . '/vendor/autoload.php';
	if ( file_exists( $autoload_filepath ) && ! class_exists( '\WooCommerce\PayPalCommerce\PluginModule' ) ) {
		require $autoload_filepath;
	}

	/**
	 * Initialize the plugin and its modules.
	 */
	function init() {
		$root_dir = __DIR__;

		if ( ! is_woocommerce_activated() ) {
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
			/**
			 * The hook fired after the plugin bootstrap with the app services container as parameter.
			 */
			do_action( 'woocommerce_paypal_payments_built_container', $app_container );
		}
	}

	add_action(
		'plugins_loaded',
		function () {
			init();

			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data              = get_plugin_data( __DIR__ . '/woocommerce-paypal-payments.php' );
			$plugin_version           = $plugin_data['Version'] ?? null;
			$installed_plugin_version = get_option( 'woocommerce-ppcp-version' );
			if ( $installed_plugin_version !== $plugin_version ) {
				/**
				 * The hook fired when the plugin is installed or updated.
				 */
				do_action( 'woocommerce_paypal_payments_gateway_migrate' );

				if ( $installed_plugin_version ) {
					/**
					 * The hook fired when the plugin is updated.
					 */
					do_action( 'woocommerce_paypal_payments_gateway_migrate_on_update' );
				}
				update_option( 'woocommerce-ppcp-version', $plugin_version );
			}
		}
	);
	register_activation_hook(
		__FILE__,
		function () {
			init();
			/**
			 * The hook fired in register_activation_hook.
			 */
			do_action( 'woocommerce_paypal_payments_gateway_activate' );
		}
	);
	register_deactivation_hook(
		__FILE__,
		function () {
			init();
			/**
			 * The hook fired in register_deactivation_hook.
			 */
			do_action( 'woocommerce_paypal_payments_gateway_deactivate' );
		}
	);

	// Add "Settings" link to Plugins screen.
	add_filter(
		'plugin_action_links_' . plugin_basename( __FILE__ ),
		function( $links ) {
			if ( ! is_woocommerce_activated() ) {
				return $links;
			}

			array_unshift(
				$links,
				sprintf(
					'<a href="%1$s">%2$s</a>',
					admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID ),
					__( 'Settings', 'woocommerce-paypal-payments' )
				)
			);

			return $links;
		}
	);

	// Add links below the description on the Plugins page.
	add_filter(
		'plugin_row_meta',
		function( $links, $file ) {
			if ( plugin_basename( __FILE__ ) !== $file ) {
				return $links;
			}

			return array_merge(
				$links,
				array(
					sprintf(
						'<a target="_blank" href="%1$s">%2$s</a>',
						'https://woocommerce.com/document/woocommerce-paypal-payments/',
						__( 'Documentation', 'woocommerce-paypal-payments' )
					),
					sprintf(
						'<a target="_blank" href="%1$s">%2$s</a>',
						'https://woocommerce.com/document/woocommerce-paypal-payments/#get-help',
						__( 'Get help', 'woocommerce-paypal-payments' )
					),
					sprintf(
						'<a target="_blank" href="%1$s">%2$s</a>',
						'https://woocommerce.com/feature-requests/woocommerce-paypal-payments/',
						__( 'Request a feature', 'woocommerce-paypal-payments' )
					),
					sprintf(
						'<a target="_blank" href="%1$s">%2$s</a>',
						'https://github.com/woocommerce/woocommerce-paypal-payments/issues/new?assignees=&labels=type%3A+bug&template=bug_report.md',
						__( 'Submit a bug', 'woocommerce-paypal-payments' )
					),
				)
			);
		},
		10,
		2
	);

	add_action(
		'before_woocommerce_init',
		function() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool true if WooCommerce is active, otherwise false.
	 */
	function is_woocommerce_activated(): bool {
		return class_exists( 'woocommerce' );
	}

} )();
