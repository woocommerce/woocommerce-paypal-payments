<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Helper;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Cart;
/**
 * Fallback calculator for complex product types (bookings, bundles, composites) that
 * need WooCommerce's full cart engine to compute accurate totals.
 *
 * Creates a throwaway WC_Cart with session hooks disabled so it never persists state,
 * never touches WC()->cart, and cleans up via try/finally even on exceptions.
 */
class IsolatedCartSimulator
{
    private \WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper $cart_products;
    private LoggerInterface $logger;
    public function __construct(\WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper $cart_products, LoggerInterface $logger)
    {
        $this->cart_products = $cart_products;
        $this->logger = $logger;
    }
    /**
     * Adds the given products to an isolated cart, runs calculate_totals(),
     * and returns the resulting total and shipping fee. The isolated cart is
     * discarded afterward; WC()->cart is never modified.
     *
     * @return array{total: float, shipping_fee: float}
     * @throws Exception If simulation fails.
     */
    public function simulate(array $products): array
    {
        $cart = null;
        $simulation_filter = function () {
            return \true;
        };
        add_filter('woocommerce_paypal_payments_is_simulating_cart', $simulation_filter);
        try {
            $cart = $this->create_isolated_cart();
            $this->cart_products->set_cart($cart);
            $this->cart_products->add_products($products);
            $cart->calculate_totals();
            return array('total' => (float) $cart->get_total('numeric'), 'shipping_fee' => (float) $cart->get_shipping_total());
        } catch (Exception $e) {
            $this->logger->error('Cart simulation failed: ' . $e->getMessage());
            throw $e;
        } finally {
            remove_filter('woocommerce_paypal_payments_is_simulating_cart', $simulation_filter);
            if ($cart instanceof WC_Cart) {
                $this->cleanup_cart($cart);
            }
            // Restore cart_products to use the real cart if available.
            if (WC()->cart) {
                $this->cart_products->set_cart(WC()->cart);
            }
        }
    }
    private function create_isolated_cart(): WC_Cart
    {
        // Prevent the new cart's session from registering hooks (persistence, cookies, etc.).
        $prevent_session = function () {
            return \false;
        };
        add_filter('woocommerce_cart_session_initialize', $prevent_session);
        $cart = new WC_Cart();
        remove_filter('woocommerce_cart_session_initialize', $prevent_session);
        // Remove hooks that WC_Cart::__construct() registers to prevent double-firing
        // and interference with the real cart's hooks.
        remove_action('woocommerce_add_to_cart', array($cart, 'calculate_totals'), 20);
        remove_action('woocommerce_applied_coupon', array($cart, 'calculate_totals'), 20);
        remove_action('woocommerce_removed_coupon', array($cart, 'calculate_totals'), 20);
        remove_action('woocommerce_cart_item_removed', array($cart, 'calculate_totals'), 20);
        remove_action('woocommerce_cart_item_restored', array($cart, 'calculate_totals'), 20);
        remove_action('woocommerce_check_cart_items', array($cart, 'check_cart_items'), 1);
        remove_action('woocommerce_check_cart_items', array($cart, 'check_cart_coupons'), 1);
        remove_action('woocommerce_after_checkout_validation', array($cart, 'check_customer_coupons'), 1);
        return $cart;
    }
    private function cleanup_cart(WC_Cart $cart): void
    {
        try {
            $this->cart_products->remove_cart_items();
        } catch (Exception $e) {
            $this->logger->warning('Cart simulation cleanup failed: ' . $e->getMessage());
        }
    }
}
