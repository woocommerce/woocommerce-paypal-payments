<?php

/**
 * Compatibility layer for WooCommerce Gift Cards plugin.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\Button\Helper\Context;
/**
 * Provides WooCommerce Gift Cards plugin compatibility.
 *
 * The WC Gift Cards plugin applies its discount directly via WC_Cart::set_total()
 * and WC_Order::set_total(), bypassing the standard coupon/fee getters. This class
 * supplies the missing amounts to the PayPal order amount breakdown via the
 * extra-discount filters.
 *
 * Two complementary hooks manage the WC session value:
 *
 * 1. clear_on_cart_page() via template_redirect — fires on every real page load
 *    regardless of whether WC recalculates, guaranteeing the session is reset to 0
 *    when the customer is on the cart page with WC_GC UI disabled.
 *
 * 2. store_cart_discount() via woocommerce_after_calculate_totals — stores the live
 *    discount from WC_GC when in a checkout context.
 *    Skipped outside of checkout when the GC UI is disabled on the cart page,
 *    since the discount is not shown there and should not be charged.
 */
class WcGiftCardsCompat
{
    private const SESSION_KEY = 'ppcp_gc_cart_discount';
    /**
     * @var Context
     */
    private Context $context;
    /**
     * @param Context $context The button context helper.
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }
    /**
     * Registers the hooks.
     */
    public function register(): void
    {
        add_action('template_redirect', array($this, 'clear_on_cart_page'));
        add_action('woocommerce_after_calculate_totals', array($this, 'store_cart_discount'), 1000);
        add_filter('woocommerce_paypal_payments_cart_extra_discount', array($this, 'cart_extra_discount'), 10, 2);
        add_filter('woocommerce_paypal_payments_order_extra_discount', array($this, 'order_extra_discount'), 10, 2);
        add_filter('woocommerce_paypal_payments_store_api_cart_extra_discount', array($this, 'store_api_cart_extra_discount'), 10, 2);
    }
    /**
     * Clears the stored discount on every cart page load (template_redirect fires
     * unconditionally, even when WC uses cached totals). This guarantees the session
     * reflects the full price the customer sees when WC_GC is configured to hide its
     * discount on the cart page.
     */
    public function clear_on_cart_page(): void
    {
        if (!function_exists('WC_GC') || !WC()->session) {
            return;
        }
        $context = $this->context->context();
        if (in_array($context, array('cart', 'cart-block'), \true) && $this->is_gc_disabled_on_cart()) {
            WC()->session->set(self::SESSION_KEY, 0.0);
        }
    }
    /**
     * Stores the applied gift card discount when WC recalculates on the checkout
     * page. Skips this outside of checkout when gift cards are disabled on cart.
     *
     * @param \WC_Cart $cart The WooCommerce cart.
     */
    public function store_cart_discount(\WC_Cart $cart): void
    {
        if (!function_exists('WC_GC') || !WC_GC()->cart || !WC()->session) {
            return;
        }
        if (!$this->context->is_checkout() && $this->is_gc_disabled_on_cart()) {
            return;
        }
        $totals = WC_GC()->cart->get_account_totals_breakdown();
        $gc_discount = (float) ($totals['cart_total'] ?? 0.0) - (float) ($totals['remaining_total'] ?? 0.0);
        WC()->session->set(self::SESSION_KEY, $gc_discount ?: 0.0);
    }
    /**
     * Returns the total WC Gift Cards discount applied to the cart.
     *
     * @param float    $extra Current extra discount accumulated by other hooks.
     * @param \WC_Cart $cart  The WooCommerce cart.
     * @return float
     */
    public function cart_extra_discount(float $extra, \WC_Cart $cart): float
    {
        if (!function_exists('WC_GC') || !WC()->session) {
            return $extra;
        }
        $gc_discount = (float) (WC()->session->get(self::SESSION_KEY) ?? 0.0);
        return $extra + ($gc_discount ?: 0.0);
    }
    /**
     * Whether the WC Gift Cards UI is disabled on the cart page (the default).
     * When disabled, the discount is not shown there and should not be applied
     * to a PayPal order created from the cart page.
     *
     * @return bool
     */
    private function is_gc_disabled_on_cart(): bool
    {
        return 'no' !== get_option('wc_gc_disable_cart_ui', 'yes');
    }
    /**
     * Returns the WC Gift Cards discount for the Store API (shipping callback on address change).
     * Must return an integer in minor units (e.g. cents for USD) to match the filter contract.
     *
     * @param int $extra Current extra discount in minor units.
     * @return int
     */
    public function store_api_cart_extra_discount(int $extra): int
    {
        if (!function_exists('WC_GC') || !WC()->session) {
            return $extra;
        }
        $gc_discount = (float) (WC()->session->get(self::SESSION_KEY) ?? 0.0);
        if ($gc_discount <= 0.0) {
            return $extra;
        }
        return $extra + (int) round($gc_discount * 10 ** wc_get_price_decimals());
    }
    /**
     * Returns the total WC Gift Cards discount applied to the order.
     *
     * @param float     $extra Current extra discount accumulated by other hooks.
     * @param \WC_Order $order The WooCommerce order.
     * @return float
     */
    public function order_extra_discount(float $extra, \WC_Order $order): float
    {
        if (!function_exists('WC_GC') || !WC_GC()->order) {
            return $extra;
        }
        $gift_cards = WC_GC()->order->get_gift_cards($order);
        $extra += (float) ($gift_cards['total'] ?? 0.0);
        return $extra;
    }
}
