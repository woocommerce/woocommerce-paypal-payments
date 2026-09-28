<?php

/**
 * Coupon Resolution Builder for Agentic Commerce.
 *
 * Builds resolution options for coupon validation issues.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator;

use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
/**
 * Builds resolution options for coupon validation issues.
 */
class CouponResolutionBuilder
{
    /**
     * Builds resolutions from config keys, with special handling for stacking.
     *
     * @return ResolutionOption[] The resolution options.
     */
    public function build_resolution_options(string $issue_type, array $keys, string $code, array $context, string $currency): array
    {
        if ($issue_type === 'COUPON_STACKING_NOT_ALLOWED') {
            return $this->build_stacking_resolutions($code, $context, $currency);
        }
        $resolutions = array();
        foreach ($keys as $key) {
            $resolution = $this->build_resolution_by_key($key, $context, $currency);
            if ($resolution) {
                $resolutions[] = $resolution;
            }
        }
        return $resolutions;
    }
    /**
     * Dispatches to the appropriate resolution factory method.
     *
     * @return ResolutionOption|null The resolution option or null if key not recognized.
     */
    private function build_resolution_by_key(string $key, array $context, string $currency): ?ResolutionOption
    {
        switch ($key) {
            case 'try_different':
                return ResolutionOption::create_apply_different_coupon()->label('Try a different coupon code')->priority(Priority::HIGH);
            case 'remove':
                return ResolutionOption::create_remove_coupon()->label('Continue without coupon')->priority(Priority::MEDIUM);
            case 'modify_cart':
                return ResolutionOption::create_modify_cart()->label('Add eligible items to use this coupon')->priority(Priority::HIGH);
            case 'view_available':
                return ResolutionOption::create_redirect_to_merchant()->label('View available offers')->priority(Priority::LOW);
            case 'suggest_alternative':
                return ResolutionOption::create_apply_different_coupon()->label('Try a different coupon')->priority(Priority::MEDIUM);
            case 'add_items_to_minimum':
                $formatted_amount = isset($context['shortage_amount']) ? Money::create($context['shortage_amount'], $currency)->to_price() : '';
                return ResolutionOption::create_modify_cart()->label(sprintf('Add %s more to qualify', $formatted_amount))->priority(Priority::HIGH)->set_meta('amount_needed', $formatted_amount);
            case 'continue_without':
                return ResolutionOption::create_remove_coupon()->label('Continue without coupon')->priority(Priority::LOW);
            default:
                return null;
        }
    }
    /**
     * Builds stacking-specific resolutions with savings comparison.
     *
     * @return ResolutionOption[] The resolution options.
     */
    private function build_stacking_resolutions(string $code, array $context, string $currency): array
    {
        $current_discount = $context['current_discount'] ?? '0.00';
        $attempted_discount = $context['attempted_discount'] ?? '0.00';
        $attempted_coupon = $context['attempted_coupon'] ?? 'other';
        $formatted_current = Money::create($current_discount, $currency)->to_price();
        $formatted_attempted = Money::create($attempted_discount, $currency)->to_price();
        return array(ResolutionOption::create_keep_current_coupon()->label(sprintf('Keep %s (saves %s)', $code, $formatted_current))->priority(Priority::HIGH)->set_meta('savings', $formatted_current), ResolutionOption::create_apply_different_coupon()->label(sprintf('Switch to %s (saves %s)', $attempted_coupon, $formatted_attempted))->priority(Priority::LOW)->set_meta('savings', $formatted_attempted));
    }
}
