<?php
/**
 * PayPal Express Checkout helper class.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

/**
 * Helper class with various constants associated to the PayPal Express Checkout plugin, as well as methods for figuring
 * out the status of the gateway.
 */
class PPECHelper {

	/**
	 * The PayPal Express Checkout gateway ID.
	 */
	const PPEC_GATEWAY_ID = 'ppec_paypal';

	/**
	 * The path to the PayPal Express Checkout main plugin file.
	 */
	const PPEC_PLUGIN_FILE = 'woocommerce-gateway-paypal-express-checkout/woocommerce-gateway-paypal-express-checkout.php';

	/**
	 * Option name for PayPal Express Checkout settings.
	 */
	const PPEC_SETTINGS_OPTION_NAME = 'woocommerce_ppec_paypal_settings';


	/**
	 * Checks if the PayPal Express Checkout plugin was configured previously.
	 *
	 * @return bool
	 */
	public static function is_plugin_configured() {
		return is_array( get_option( self::PPEC_SETTINGS_OPTION_NAME ) );
	}

	/**
	 * Checks if the PayPal Express Checkout plugin is active.
	 *
	 * @return bool
	 */
	public static function is_plugin_active() {
		return is_callable( 'wc_gateway_ppec' );
	}

	/**
	 * Checks whether the PayPal Express Checkout plugin is available (plugin active and gateway configured).
	 *
	 * @return bool
	 */
	public static function is_gateway_available() {
		if ( ! self::is_plugin_active() || ! is_callable( 'wc_gateway_ppec' ) ) {
			return false;
		}

		$ppec = wc_gateway_ppec();
		return is_object( $ppec ) && $ppec->settings && $ppec->settings->get_active_api_credentials();
	}

	/**
	 * Checks whether the site has subscriptions handled through PayPal Express Checkout.
	 *
	 * @return bool
	 */
	public static function site_has_ppec_subscriptions() {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = %s AND p.post_status != %s AND pm.meta_key = %s AND pm.meta_value = %s LIMIT 1",
				'shop_subscription',
				'trash',
				'_payment_method',
				self::PPEC_GATEWAY_ID
			)
		);

		return ! empty( $result );
	}

	/**
	 * Checks whether the compatibility layer for PPEC Subscriptions should be initialized.
	 *
	 * @return bool
	 */
	public static function use_ppec_compat_layer_for_subscriptions() {
		return ( ! self::is_gateway_available() ) && self::site_has_ppec_subscriptions() && apply_filters( 'woocommerce_paypal_payments_process_legacy_subscriptions', true );
	}

}
