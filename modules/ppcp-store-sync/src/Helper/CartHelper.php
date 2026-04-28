<?php

/**
 * Cart Helper for Agentic Commerce.
 *
 * Provides convenience methods for accessing and calculating cart data.
 * Schema classes remain pure data structures; all convenience logic goes here.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WC_Cart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Address;
class CartHelper
{
    /**
     * Derives currency from the first cart item's price.
     */
    public static function currency(PayPalCart $cart, string $default = ''): string
    {
        $items = $cart->items();
        if (empty($items)) {
            return $default;
        }
        $first_item = $items[0];
        $price = $first_item->price();
        if (!$price) {
            return $default;
        }
        return $price->currency_code() ?? $default;
    }
    /**
     * Formats a price with the correct currency symbol and position.
     *
     * Uses WooCommerce's wc_price() and strips HTML tags for plain text.
     *
     * @param string     $amount The amount to format (e.g., "99.00").
     * @param PayPalCart $cart The cart for currency context.
     * @return string Formatted price with symbol (e.g., "$99.00", "99.00€", "$ 99.00").
     */
    public static function format_price(string $amount, PayPalCart $cart): string
    {
        $currency_code = self::currency($cart);
        $args = array('decimals' => 2);
        if ($currency_code) {
            $args['currency'] = $currency_code;
        }
        $formatted_html = wc_price((float) $amount, $args);
        return html_entity_decode(wp_strip_all_tags($formatted_html), \ENT_QUOTES | \ENT_HTML5, get_bloginfo('charset'));
    }
    /**
     * Sums (price * quantity) for each item. Items without a price are treated as 0.0.
     */
    public static function cart_item_total(PayPalCart $cart): float
    {
        return array_reduce($cart->items(), static function (float $cart_total, CartItem $item): float {
            $price = $item->price();
            if (!$price || !$price->value()) {
                return $cart_total;
            }
            return $cart_total + $price->value() * (float) $item->quantity();
        }, 0.0);
    }
    /**
     * Formats a price value to two decimal places.
     *
     * @param float $value The price value to format.
     * @return string The formatted price (e.g., "123.45").
     */
    public static function format_decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
    public static function full_customer_name(PayPalCart $cart, string $default = ''): string
    {
        $full_name = '';
        $customer = $cart->customer();
        if ($customer && $customer->name()) {
            $name = $customer->name();
            $first_name = $name['given_name'] ?? '';
            $last_name = $name['surname'] ?? '';
            $full_name = trim("{$first_name} {$last_name}");
        }
        return $full_name ?: $default;
    }
    /**
     * @return array{
     *     address_line_1: string,
     *     address_line_2: string,
     *     admin_area_2: string,
     *     admin_area_1: string,
     *     postal_code: string,
     *     country_code: string
     * }
     */
    public static function shipping_address_array(PayPalCart $cart): array
    {
        return self::address_array($cart->shipping_address());
    }
    /**
     * @return array{
     *     address_line_1: string,
     *     address_line_2: string,
     *     admin_area_2: string,
     *     admin_area_1: string,
     *     postal_code: string,
     *     country_code: string
     * }
     */
    public static function billing_address_array(PayPalCart $cart): array
    {
        return self::address_array($cart->billing_address());
    }
    private static function address_array(?Address $address): array
    {
        if (!$address) {
            return array('address_line_1' => '', 'address_line_2' => '', 'admin_area_2' => '', 'admin_area_1' => '', 'postal_code' => '', 'country_code' => '');
        }
        return array('address_line_1' => $address->address_line_1() ?? '', 'address_line_2' => $address->address_line_2() ?? '', 'admin_area_2' => $address->admin_area_2() ?? '', 'admin_area_1' => $address->admin_area_1() ?? '', 'postal_code' => $address->postal_code() ?? '', 'country_code' => $address->country_code() ?? '');
    }
    /**
     * Calculate cart totals from WooCommerce cart.
     *
     * @param WC_Cart $wc_cart       The WooCommerce cart.
     * @param string  $currency_code The currency code.
     * @return array|null The totals array, or null if not calculable.
     */
    public static function calculate_totals(WC_Cart $wc_cart, string $currency_code): ?array
    {
        $item_total = (float) $wc_cart->get_cart_contents_total();
        $discount_total = (float) $wc_cart->get_discount_total();
        $shipping_total = (float) $wc_cart->get_shipping_total();
        $tax_total = (float) $wc_cart->get_total_tax();
        $cart_total = (float) $wc_cart->get_total('edit');
        if (!$currency_code || $item_total <= 0 || $cart_total <= 0) {
            return null;
        }
        $totals = array('subtotal' => self::money($currency_code, $item_total), 'shipping' => self::money($currency_code, $shipping_total), 'tax' => self::money($currency_code, $tax_total), 'total' => self::money($currency_code, $cart_total));
        if ($discount_total > 0) {
            $totals['discount'] = self::money($currency_code, $discount_total);
        }
        return $totals;
    }
    /**
     * Format a money value for API responses.
     *
     * @param string $currency_code The currency code.
     * @param float  $value         The money value.
     * @return array Money object with currency_code and value.
     */
    public static function money(string $currency_code, float $value): array
    {
        return array('currency_code' => $currency_code, 'value' => number_format($value, 2));
    }
}
