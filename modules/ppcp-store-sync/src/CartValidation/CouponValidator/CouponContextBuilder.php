<?php

/**
 * Coupon Context Builder for Agentic Commerce.
 *
 * Builds enhanced context data for coupon validation issues.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator;

use WC_Coupon;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\PricingErrorContext;
/**
 * Builds context data for coupon validation issues.
 */
class CouponContextBuilder
{
    private ProductManager $product_manager;
    private \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator $discount_calculator;
    public function __construct(ProductManager $product_manager, \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator $discount_calculator)
    {
        $this->product_manager = $product_manager;
        $this->discount_calculator = $discount_calculator;
    }
    /**
     * Builds context by calling declared context builders.
     *
     * @return array The built context.
     */
    public function build_coupon_context(string $issue_type, string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $builders, array $extra = array()): array
    {
        $context = array('specific_issue' => $issue_type, 'coupon_code' => $code);
        foreach ($builders as $builder) {
            $builder_context = $this->call_builder($builder, $code, $store_cart, $wc_coupon, array_merge($context, $extra));
            $context = array_merge($context, $builder_context);
        }
        return array_merge($context, $extra);
    }
    /**
     * Dispatches to the appropriate builder method.
     *
     * @return array The context data from the builder.
     */
    private function call_builder(string $builder, string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        switch ($builder) {
            case 'alternatives':
                return $this->build_alternatives($code, $store_cart, $wc_coupon, $extra);
            case 'expiration':
                return $this->build_expiration($code, $store_cart, $wc_coupon, $extra);
            case 'usage_limits':
                return $this->build_usage_limits($code, $store_cart, $wc_coupon, $extra);
            case 'minimum_spend':
                return $this->build_minimum_spend($code, $store_cart, $wc_coupon, $extra);
            case 'maximum_spend':
                return $this->build_maximum_spend($code, $store_cart, $wc_coupon, $extra);
            case 'eligible_items':
                return $this->build_eligible_items($code, $store_cart, $wc_coupon, $extra);
            case 'stacking':
                return $this->build_stacking($code, $store_cart, $wc_coupon, $extra);
            case 'email_restriction':
                return $this->build_email_restriction($code, $store_cart, $wc_coupon, $extra);
            default:
                return array();
        }
    }
    /**
     * Builds alternative coupons context.
     */
    private function build_alternatives(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        $alternatives = $this->get_alternative_coupons($code, $extra['specific_issue'] ?? 'COUPON_INVALID', $store_cart);
        if (empty($alternatives)) {
            return array();
        }
        return array('suggested_alternatives' => $alternatives, 'available_coupons' => \true);
    }
    /**
     * Builds expiration context.
     */
    private function build_expiration(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        if (!$wc_coupon) {
            return array();
        }
        $expiration_date = $wc_coupon->get_date_expires();
        if (!$expiration_date) {
            return array();
        }
        return array('expiration_date' => $expiration_date->getTimestamp());
    }
    /**
     * Builds usage limits context.
     */
    private function build_usage_limits(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        if (!$wc_coupon) {
            return array();
        }
        return array('usage_limit' => $wc_coupon->get_usage_limit(), 'current_usage' => $wc_coupon->get_usage_count());
    }
    /**
     * Builds minimum spend context.
     */
    private function build_minimum_spend(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        if (!$wc_coupon) {
            return array();
        }
        $subtotal = array_reduce($store_cart->cart_items(), static function (float $total, StoreCartItem $item): float {
            return $total + $item->real_price() * (float) $item->paypal_item()->quantity();
        }, 0.0);
        $minimum = (float) $wc_coupon->get_minimum_amount();
        $shortage = max(0, $minimum - $subtotal);
        return array('minimum_required' => Money::create($minimum)->to_decimal(), 'current_subtotal' => Money::create($subtotal)->to_decimal(), 'shortage_amount' => Money::create($shortage)->to_decimal(), 'currency_code' => $store_cart->currency());
    }
    /**
     * Builds maximum spend context.
     */
    private function build_maximum_spend(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        if (!$wc_coupon) {
            return array();
        }
        $subtotal = array_reduce($store_cart->cart_items(), static function (float $total, StoreCartItem $item): float {
            return $total + $item->real_price() * (float) $item->paypal_item()->quantity();
        }, 0.0);
        $maximum = (float) $wc_coupon->get_maximum_amount();
        return array('maximum_allowed' => Money::create($maximum)->to_decimal(), 'current_subtotal' => Money::create($subtotal)->to_decimal(), 'currency_code' => $store_cart->currency());
    }
    /**
     * Builds eligible items context.
     */
    private function build_eligible_items(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        if (!$wc_coupon) {
            return array();
        }
        $eligible = $this->get_eligible_items($wc_coupon, $store_cart);
        if (empty($eligible)) {
            return array();
        }
        return array('eligible_items' => $eligible);
    }
    /**
     * Builds stacking conflict context.
     */
    private function build_stacking(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        $other_codes = $extra['other_codes'] ?? array();
        if (empty($other_codes)) {
            return array();
        }
        $current_discount = $wc_coupon ? $this->discount_calculator->calculate_discount_amount($wc_coupon, $store_cart->paypal_cart()) : '0.00';
        $attempted_discount = '0.00';
        // Normalize coupon code to match WooCommerce's case-insensitive behavior.
        $normalized_other_code = wc_sanitize_coupon_code($other_codes[0]);
        $other_coupon = new WC_Coupon($normalized_other_code);
        if ($other_coupon->get_id()) {
            $attempted_discount = $this->discount_calculator->calculate_discount_amount($other_coupon, $store_cart->paypal_cart());
        }
        return array('current_coupon' => $code, 'attempted_coupon' => $other_codes[0], 'attempted_coupons' => $other_codes, 'current_discount' => $current_discount, 'attempted_discount' => $attempted_discount);
    }
    /**
     * Builds email restriction context.
     */
    private function build_email_restriction(string $code, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra): array
    {
        if (!$wc_coupon) {
            return array();
        }
        $restrictions = $wc_coupon->get_email_restrictions();
        if (empty($restrictions)) {
            return array();
        }
        return array('email_restricted' => \true);
    }
    /**
     * Gets alternative coupon suggestions via filter.
     */
    private function get_alternative_coupons(string $failed_code, string $reason, StorePayPalCart $store_cart): array
    {
        return apply_filters('woocommerce_paypal_payments_store_sync_suggested_alternative_coupons', array(), $failed_code, $reason, $store_cart->paypal_cart());
    }
    /**
     * Builds a typed IssueContext instance from a resolved context array.
     *
     * Returns null for issue types that have no matching context class.
     *
     * @param string $issue_type  The coupon issue type (e.g. 'COUPON_EXPIRED').
     * @param string $coupon_code The coupon code that triggered the issue.
     * @param array  $args        The flat context array built by build_coupon_context().
     * @return IssueContext|null
     */
    public function build_coupon_issue_context(string $issue_type, string $coupon_code, array $args): ?IssueContext
    {
        $context = null;
        switch ($issue_type) {
            case 'COUPON_EXPIRED':
                $context = PricingErrorContext::create_discount_expired();
                break;
            case 'USAGE_LIMIT_EXCEEDED':
                $context = PricingErrorContext::create_discount_usage_limit_exceeded();
                break;
            case 'MINIMUM_ORDER_NOT_MET':
                $context = PricingErrorContext::create_discount_minimum_not_met();
                break;
            case 'COUPON_NOT_APPLICABLE':
            case 'COUPON_EMAIL_RESTRICTED':
                $context = PricingErrorContext::create_discount_customer_ineligible();
                break;
            case 'COUPON_STACKING_NOT_ALLOWED':
                $context = PricingErrorContext::create_promotional_conflict();
                break;
        }
        if (!$context) {
            return null;
        }
        $context->coupon_code($coupon_code);
        if (isset($args['expiration_date'])) {
            $context->expiration_date((int) $args['expiration_date']);
        }
        if (isset($args['usage_limit']) && is_numeric($args['usage_limit'])) {
            $context->usage_limit((int) $args['usage_limit']);
        }
        if (isset($args['minimum_required'])) {
            $context->minimum_order_amount((string) $args['minimum_required']);
        }
        if (isset($args['current_usage'])) {
            $context->current_usage((int) $args['current_usage']);
        }
        if (isset($args['currency_code'])) {
            $context->currency_code((string) $args['currency_code']);
        }
        if (isset($args['current_discount'])) {
            $context->discount_amount((string) $args['current_discount']);
        }
        return $context;
    }
    /**
     * Gets eligible items for a coupon via filter.
     *
     * Uses WooCommerce's native is_valid_for_product() method to check eligibility,
     * which handles all coupon restrictions including product IDs, categories,
     * excluded items, sale items, and third-party plugin logic.
     *
     * @return array Array of eligible variant IDs.
     */
    private function get_eligible_items(WC_Coupon $wc_coupon, StorePayPalCart $store_cart): array
    {
        $eligible = array();
        foreach ($store_cart->cart_items() as $item) {
            $product = $this->product_manager->find_product($item->paypal_item());
            if (!$product) {
                continue;
            }
            // Use WooCommerce's native validation which handles all restrictions
            // including products, categories, exclusions, sale items, and plugin extensions.
            if ($wc_coupon->is_valid_for_product($product, array('data' => $product))) {
                $eligible[] = $item->paypal_item()->variant_id();
            }
        }
        return $eligible;
    }
}
