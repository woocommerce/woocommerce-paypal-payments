<?php

/**
 * The Checkout helper.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use DateTime;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
/**
 * CheckoutHelper class.
 */
class CheckoutHelper
{
    /**
     * Checks if amount is allowed within the given range.
     *
     * @param float $minimum Minimum amount.
     * @param float $maximum Maximum amount.
     * @return bool
     */
    public function is_checkout_amount_allowed(float $minimum, float $maximum): bool
    {
        $cart = WC()->cart ?? null;
        if ($cart && !is_checkout_pay_page()) {
            $cart_total = (float) $cart->get_total('numeric');
            if ($cart_total < $minimum || $cart_total > $maximum) {
                return \false;
            }
        }
        if (is_wc_endpoint_url('order-pay')) {
            /**
             * Needed for WordPress `query_vars`.
             *
             * @psalm-suppress InvalidGlobal
             */
            global $wp;
            if (isset($wp->query_vars['order-pay']) && absint($wp->query_vars['order-pay']) > 0) {
                $order_id = absint($wp->query_vars['order-pay']);
                $order = wc_get_order($order_id);
                if (is_a($order, WC_Order::class)) {
                    $order_total = (float) $order->get_total();
                    if ($order_total < $minimum || $order_total > $maximum) {
                        return \false;
                    }
                }
            }
        }
        return \true;
    }
    /**
     * Ensures date is valid and at least 18 years back.
     *
     * @param string $date The date.
     * @param string $format The date format.
     * @return bool
     */
    public function validate_birth_date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        if (\false === $d) {
            return \false;
        }
        if ($date !== $d->format($format)) {
            return \false;
        }
        $date_time = strtotime($date);
        if ($date_time && time() < strtotime('+18 years', $date_time)) {
            return \false;
        }
        if ($date_time < strtotime('-100 years', time())) {
            return \false;
        }
        return \true;
    }
    /**
     * Ensures product is neither downloadable nor virtual.
     *
     * @param WC_Product $product WC product (can be a variation).
     * @return bool
     */
    public function is_physical_product(WC_Product $product): bool
    {
        if ($product->is_downloadable() || $product->is_virtual()) {
            return \false;
        }
        return \true;
    }
}
