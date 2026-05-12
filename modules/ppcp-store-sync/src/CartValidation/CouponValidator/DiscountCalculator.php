<?php

/**
 * Coupon Discount Calculator for Agentic Commerce.
 *
 * Calculates coupon discount amounts using WooCommerce's native WC_Discounts.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator;

use Exception;
use WC_Coupon;
use WC_Discounts;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
/**
 * Calculates discount amounts for coupons using WooCommerce's native discount calculation.
 */
class DiscountCalculator
{
    private ProductManager $product_manager;
    public function __construct(ProductManager $product_manager)
    {
        $this->product_manager = $product_manager;
    }
    /**
     * Calculates discount amount using WC_Discounts.
     *
     * This method calculates the theoretical discount amount without validation.
     * Used for showing discount comparisons in stacking conflicts, where coupons
     * may not be individually valid but we still want to show their discount amounts.
     *
     * @param WC_Coupon  $wc_coupon The WC coupon object.
     * @param PayPalCart $cart      The cart context.
     * @return string The discount amount formatted to 2 decimals, or '0.00' if calculation fails.
     */
    public function calculate_discount_amount(WC_Coupon $wc_coupon, PayPalCart $cart): string
    {
        $discounts = $this->create_discounts_instance($cart);
        // Skip validation - we only want to calculate the discount amount.
        // Validation is handled separately by CouponValidator.
        try {
            $result = $discounts->apply_coupon($wc_coupon, \false);
        } catch (Exception $exception) {
            // Should never happen because validation is skipped, adding error handling for
            // extra safety and future-proofing.
            return '0.00';
        }
        if (is_wp_error($result)) {
            // If calculation fails even without validation, return 0.00.
            // This can happen if the coupon type is invalid or products don't match.
            return '0.00';
        }
        $totals = $discounts->get_discounts_by_coupon();
        $code = $wc_coupon->get_code();
        if (isset($totals[$code])) {
            // Handle both array (multiple items) and scalar (single value) formats.
            $discount_value = is_array($totals[$code]) ? array_sum($totals[$code]) : $totals[$code];
            return Money::create($discount_value)->to_decimal();
        }
        return '0.00';
    }
    /**
     * Creates a WC_Discounts instance from PayPal cart.
     *
     * @param PayPalCart $cart The PayPal cart.
     * @return WC_Discounts The discounts instance.
     */
    public function create_discounts_instance(PayPalCart $cart): WC_Discounts
    {
        $discounts = new WC_Discounts();
        $items = array();
        foreach ($cart->items() as $key => $item) {
            $product = $this->product_manager->find_product($item);
            if (!$product) {
                continue;
            }
            $price = $item->price();
            $item_price = $price ? $price->value() : (float) $product->get_price();
            $std_item = new \stdClass();
            $std_item->key = (string) $key;
            $std_item->object = array('data' => $product);
            $std_item->product = $product;
            $std_item->quantity = $item->quantity();
            $std_item->price = wc_add_number_precision($item_price * (float) $item->quantity());
            $items[$std_item->key] = $std_item;
        }
        if (!empty($items)) {
            $discounts->set_items($items);
        }
        return $discounts;
    }
}
