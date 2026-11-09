<?php

/**
 * Applied Coupons Builder.
 *
 * Builds the applied_coupons array for successful cart responses.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator;

use WC_Coupon;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
/**
 * Builds applied coupons data for API responses.
 */
class AppliedCouponsBuilder
{
    private \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator $discount_calculator;
    public function __construct(\WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator $discount_calculator)
    {
        $this->discount_calculator = $discount_calculator;
    }
    /**
     * Build applied_coupons array for successfully applied coupons.
     *
     * Only returns coupons when:
     * - Cart validation status is VALID
     * - Coupons have APPLY action
     * - WooCommerce classes are available
     *
     * @return array Array of applied coupon data.
     */
    public function build_applied_coupons_array(StorePayPalCart $store_cart, string $validation_status): array
    {
        if ($validation_status !== 'VALID') {
            return array();
        }
        if (!class_exists(WC_Coupon::class)) {
            return array();
        }
        $coupons = $store_cart->paypal_cart()->coupons();
        if (!$coupons) {
            return array();
        }
        // Only include coupons with APPLY action.
        $apply_coupons = array_filter($coupons, static fn($coupon) => $coupon->action() === 'APPLY');
        if (empty($apply_coupons)) {
            return array();
        }
        $applied = array();
        $currency_code = $store_cart->currency();
        foreach ($apply_coupons as $coupon) {
            $code = $coupon->code();
            // Normalize coupon code to match WooCommerce's case-insensitive behavior.
            $normalized_code = wc_sanitize_coupon_code($code);
            $wc_coupon = new WC_Coupon($normalized_code);
            if (!$wc_coupon->get_id()) {
                continue;
            }
            // Calculate discount amount.
            $discount_amount = $this->discount_calculator->calculate_discount_amount($wc_coupon, $store_cart->paypal_cart());
            $applied[] = array('code' => $code, 'description' => $wc_coupon->get_description() ?: $wc_coupon->get_discount_type() . ' discount', 'discount_amount' => array('currency_code' => $currency_code, 'value' => $discount_amount));
        }
        return $applied;
    }
    /**
     * Calculate the total discount amount from applied coupons.
     *
     * Used when updating PayPal orders to include the discount in the breakdown.
     *
     * @return float Total discount amount.
     */
    public function calculate_total_discount(StorePayPalCart $store_cart): float
    {
        $applied_coupons = $this->build_applied_coupons_array($store_cart, 'VALID');
        return array_reduce($applied_coupons, static function (float $total, array $coupon): float {
            $value = $coupon['discount_amount']['value'] ?? 0;
            return $total + (float) $value;
        }, 0.0);
    }
}
