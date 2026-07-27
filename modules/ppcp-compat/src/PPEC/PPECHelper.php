<?php

/**
 * PayPal Express Checkout helper class.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */
namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Automattic\WooCommerce\Utilities\OrderUtil;
/**
 * Helper class with various constants associated to the PayPal Express Checkout plugin, as well as methods for figuring
 * out the status of the gateway.
 */
class PPECHelper
{
    /**
     * The PayPal Express Checkout gateway ID.
     */
    const PPEC_GATEWAY_ID = 'ppec_paypal';
    /**
     * Checks if the PayPal Express Checkout plugin is active.
     */
    public static function is_plugin_active(): bool
    {
        return is_callable('wc_gateway_ppec');
        // @phpstan-ignore function.impossibleType
    }
    /**
     * Checks whether the site has subscriptions handled through PayPal Express Checkout.
     *
     * @return bool
     */
    public static function site_has_ppec_subscriptions()
    {
        $has_ppec_subscriptions = get_transient('ppcp_has_ppec_subscriptions');
        if ($has_ppec_subscriptions !== \false) {
            return $has_ppec_subscriptions === 'true';
        }
        global $wpdb;
        if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            $result = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}wc_orders WHERE payment_method = %s", self::PPEC_GATEWAY_ID));
        } else {
            $result = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID\n\t\t\t\tWHERE p.post_type = %s AND p.post_status != %s AND pm.meta_key = %s AND pm.meta_value = %s LIMIT 1", 'shop_subscription', 'trash', '_payment_method', self::PPEC_GATEWAY_ID));
        }
        set_transient('ppcp_has_ppec_subscriptions', !empty($result) ? 'true' : 'false', MONTH_IN_SECONDS);
        return !empty($result);
    }
    /**
     * Checks whether the compatibility layer for PPEC Subscriptions should be initialized.
     *
     * @return bool
     */
    public static function use_ppec_compat_layer_for_subscriptions()
    {
        /**
         * The filter returning whether the compatibility layer for PPEC Subscriptions should be initialized.
         */
        return !self::is_plugin_active() && self::site_has_ppec_subscriptions() && apply_filters('woocommerce_paypal_payments_process_legacy_subscriptions', \true);
    }
}
