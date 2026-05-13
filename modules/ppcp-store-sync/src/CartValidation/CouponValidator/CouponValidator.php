<?php

/**
 * Coupon Validator for Agentic Commerce.
 *
 * Validates coupon codes using WooCommerce's WC_Discounts validation.
 * Captures numeric error codes via the woocommerce_coupon_error filter
 * for reliable error type mapping regardless of localization.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator;

use WC_Coupon;
use WC_Discounts;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Coupon;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * Validates coupons for Agentic Commerce using WooCommerce's native validation.
 */
class CouponValidator implements ValidatorInterface
{
    private \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponContextBuilder $context_builder;
    private \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator $discount_calculator;
    private \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponResolutionBuilder $resolution_builder;
    /**
     * Issue configuration.
     *
     * Each issue type declares:
     * - message: Internal message
     * - user_message: Customer-facing message template (%s = coupon code)
     * - resolutions: Array of resolution keys
     * - context_builders: Array of context builder method names to call
     */
    private const ISSUE_CONFIG = array('COUPON_NOT_EXIST' => array('message' => 'Coupon does not exist', 'user_message' => "The coupon code '%s' is not valid. Please check the code and try again.", 'resolutions' => array('remove'), 'context_builders' => array()), 'COUPON_EXPIRED' => array('message' => 'Coupon has expired', 'user_message' => "The coupon code '%s' has expired.", 'resolutions' => array('remove'), 'context_builders' => array('expiration')), 'USAGE_LIMIT_EXCEEDED' => array('message' => 'Coupon usage limit reached', 'user_message' => "The coupon code '%s' has reached its usage limit.", 'resolutions' => array('remove'), 'context_builders' => array('usage_limits')), 'MINIMUM_ORDER_NOT_MET' => array('message' => 'Minimum order amount not met', 'user_message' => "The coupon '%s' requires a minimum order of %s. Your current order is %s.", 'resolutions' => array('add_items_to_minimum', 'continue_without'), 'context_builders' => array('minimum_spend', 'eligible_items')), 'MAXIMUM_ORDER_EXCEEDED' => array('message' => 'Maximum order amount exceeded', 'user_message' => "The coupon '%s' cannot be applied to orders above %s.", 'resolutions' => array('modify_cart', 'remove'), 'context_builders' => array('maximum_spend')), 'COUPON_NOT_APPLICABLE' => array('message' => 'Coupon not applicable to cart items', 'user_message' => "The coupon '%s' is not applicable to the items in your cart.", 'resolutions' => array('modify_cart', 'remove'), 'context_builders' => array('eligible_items')), 'COUPON_STACKING_NOT_ALLOWED' => array(
        'message' => 'Coupon cannot be combined with other coupons',
        'user_message' => "The coupon '%s' cannot be combined with other coupons.",
        'resolutions' => array(),
        // Built dynamically with savings comparison.
        'context_builders' => array('stacking'),
    ), 'COUPON_ALREADY_APPLIED' => array('message' => 'Coupon already applied', 'user_message' => "The coupon '%s' has already been applied to this cart.", 'resolutions' => array('remove'), 'context_builders' => array()), 'COUPON_EMAIL_RESTRICTED' => array('message' => 'Coupon restricted to specific email addresses', 'user_message' => "The coupon '%s' is restricted to specific customers.", 'resolutions' => array('remove'), 'context_builders' => array('email_restriction')), 'COUPON_INVALID' => array('message' => 'Coupon is not valid', 'user_message' => "The coupon '%s' is not valid.", 'resolutions' => array('remove'), 'context_builders' => array()), 'COUPON_NOT_SUPPORTED' => array('message' => 'Coupons are not enabled', 'user_message' => 'This store does not accept coupon codes at this time.', 'resolutions' => array('remove'), 'context_builders' => array()));
    /**
     * Constructor.
     *
     * @param CouponContextBuilder    $context_builder     Context builder instance.
     * @param DiscountCalculator      $discount_calculator Discount calculator instance.
     * @param CouponResolutionBuilder $resolution_builder  Resolution builder instance.
     */
    public function __construct(\WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponContextBuilder $context_builder, \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator $discount_calculator, \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponResolutionBuilder $resolution_builder)
    {
        $this->context_builder = $context_builder;
        $this->discount_calculator = $discount_calculator;
        $this->resolution_builder = $resolution_builder;
    }
    /**
     * Validates coupons in the cart.
     *
     * @param StorePayPalCart $store_cart The enriched cart to validate.
     * @return ValidationIssue[]|null Array of validation issues or null if valid.
     */
    public function validate(StorePayPalCart $store_cart): ?array
    {
        $cart = $store_cart->paypal_cart();
        $coupons_to_apply = $this->get_coupons_to_apply($cart);
        if (empty($coupons_to_apply)) {
            return null;
        }
        if (!$this->is_wc_available()) {
            return null;
        }
        if (!wc_coupons_enabled()) {
            $first_coupon = reset($coupons_to_apply);
            return array($this->create_issue('COUPON_NOT_SUPPORTED', $first_coupon->code() ?? '', 'coupons', $store_cart, null));
        }
        // Check stacking first (multiple coupons with individual_use).
        $stacking_issue = $this->check_stacking_conflicts($coupons_to_apply, $store_cart);
        if ($stacking_issue) {
            return array($stacking_issue);
        }
        // Validate each coupon.
        $discounts = $this->discount_calculator->create_discounts_instance($cart);
        $issues = array();
        foreach ($coupons_to_apply as $index => $coupon) {
            $issue = $this->validate_single_coupon($coupon, $store_cart, $index, $discounts);
            if ($issue) {
                $issues[] = $issue;
            }
        }
        return $issues ?: null;
    }
    /**
     * Filters coupons with APPLY action.
     *
     * @param PayPalCart $cart The cart.
     * @return Coupon[] Array of coupons to apply.
     */
    private function get_coupons_to_apply(PayPalCart $cart): array
    {
        $coupons = $cart->coupons();
        if (!$coupons) {
            return array();
        }
        return array_values(array_filter($coupons, static fn(Coupon $c): bool => $c->action() === 'APPLY'));
    }
    /**
     * Checks if WooCommerce coupon classes are available.
     *
     * @return bool True if WC classes are available.
     */
    private function is_wc_available(): bool
    {
        return class_exists(WC_Coupon::class) && class_exists(WC_Discounts::class);
    }
    /**
     * Checks for stacking conflicts when multiple coupons are applied.
     *
     * WooCommerce default behavior: By default, multiple coupons CAN be stacked
     * unless a coupon has the "Individual use only" checkbox enabled.
     * When individual_use is true, that coupon cannot be combined with ANY other coupons.
     *
     * @return ValidationIssue|null Validation issue or null if no conflicts.
     */
    private function check_stacking_conflicts(array $coupons, StorePayPalCart $store_cart): ?ValidationIssue
    {
        if (count($coupons) < 2) {
            return null;
        }
        // Load all valid WC coupons.
        $wc_coupons = array();
        foreach ($coupons as $coupon) {
            // Normalize coupon code to match WooCommerce's case-insensitive behavior.
            $normalized_code = wc_sanitize_coupon_code($coupon->code() ?? '');
            $wc_coupon = new WC_Coupon($normalized_code);
            if (!$wc_coupon->get_id()) {
                continue;
            }
            $wc_coupons[] = array('coupon' => $coupon, 'wc_coupon' => $wc_coupon);
        }
        // Check if any coupon has individual_use enabled.
        foreach ($wc_coupons as $index => $data) {
            if ($data['wc_coupon']->get_individual_use()) {
                // Build list of OTHER coupon codes (exclude current one).
                $other_codes = array();
                foreach ($wc_coupons as $other_index => $other_data) {
                    if ($index !== $other_index) {
                        $other_codes[] = $other_data['coupon']->code();
                    }
                }
                return $this->create_issue('COUPON_STACKING_NOT_ALLOWED', $data['coupon']->code() ?? '', 'coupons', $store_cart, $data['wc_coupon'], array('other_codes' => $other_codes));
            }
        }
        return null;
    }
    /**
     * Validates a single coupon using WC_Discounts.
     */
    private function validate_single_coupon(Coupon $coupon, StorePayPalCart $store_cart, int $index, WC_Discounts $discounts): ?ValidationIssue
    {
        $code = $coupon->code() ?? '';
        $field = $index > 0 ? "coupons[{$index}]" : 'coupons';
        // Normalize coupon code to match WooCommerce's case-insensitive behavior.
        $normalized_code = wc_sanitize_coupon_code($code);
        $wc_coupon = new WC_Coupon($normalized_code);
        if (!$wc_coupon->get_id()) {
            return $this->create_issue('COUPON_NOT_EXIST', $code, $field, $store_cart, null);
        }
        // Capture error code via filter instead of relying on localized messages.
        $error_code = 0;
        $capture_error = static function (string $error_message, int $code) use (&$error_code): string {
            $error_code = $code;
            return $error_message;
        };
        add_filter('woocommerce_coupon_error', $capture_error, 10, 2);
        $result = $discounts->is_coupon_valid($wc_coupon);
        remove_filter('woocommerce_coupon_error', $capture_error, 10);
        if (is_wp_error($result)) {
            $issue_type = $this->map_error_code_to_issue_type($error_code);
            return $this->create_issue($issue_type, $code, $field, $store_cart, $wc_coupon);
        }
        return null;
    }
    /**
     * Maps WC_Coupon error code to issue type.
     *
     * Error codes are captured via the woocommerce_coupon_error filter
     * and mapped to our issue types.
     *
     * @see WC_Coupon for error code constants (E_WC_COUPON_*).
     *
     * @param int $error_code The numeric error code from WC_Coupon.
     * @return string The mapped issue type.
     */
    private function map_error_code_to_issue_type(int $error_code): string
    {
        $error_code_map = array(
            100 => 'COUPON_INVALID',
            // E_WC_COUPON_INVALID_FILTERED.
            101 => 'COUPON_INVALID',
            // E_WC_COUPON_INVALID_REMOVED.
            102 => 'COUPON_EMAIL_RESTRICTED',
            // E_WC_COUPON_NOT_YOURS_REMOVED.
            103 => 'COUPON_ALREADY_APPLIED',
            // E_WC_COUPON_ALREADY_APPLIED.
            104 => 'COUPON_STACKING_NOT_ALLOWED',
            // E_WC_COUPON_ALREADY_APPLIED_INDIV_USE_ONLY.
            105 => 'COUPON_NOT_EXIST',
            // E_WC_COUPON_NOT_EXIST.
            106 => 'USAGE_LIMIT_EXCEEDED',
            // E_WC_COUPON_USAGE_LIMIT_REACHED.
            107 => 'COUPON_EXPIRED',
            // E_WC_COUPON_EXPIRED.
            108 => 'MINIMUM_ORDER_NOT_MET',
            // E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET.
            109 => 'COUPON_NOT_APPLICABLE',
            // E_WC_COUPON_NOT_APPLICABLE.
            110 => 'COUPON_NOT_APPLICABLE',
            // E_WC_COUPON_NOT_VALID_SALE_ITEMS.
            112 => 'MAXIMUM_ORDER_EXCEEDED',
            // E_WC_COUPON_MAX_SPEND_LIMIT_MET.
            113 => 'COUPON_NOT_APPLICABLE',
            // E_WC_COUPON_EXCLUDED_PRODUCTS.
            114 => 'COUPON_NOT_APPLICABLE',
            // E_WC_COUPON_EXCLUDED_CATEGORIES.
            115 => 'USAGE_LIMIT_EXCEEDED',
            // E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK.
            116 => 'USAGE_LIMIT_EXCEEDED',
        );
        return $error_code_map[$error_code] ?? 'COUPON_INVALID';
    }
    /**
     * Creates a CouponInvalid issue - the single point of issue creation.
     */
    private function create_issue(string $issue_type, string $code, string $field, StorePayPalCart $store_cart, ?WC_Coupon $wc_coupon, array $extra_context = array()): ValidationIssue
    {
        $config = self::ISSUE_CONFIG[$issue_type] ?? self::ISSUE_CONFIG['COUPON_INVALID'];
        $paypal_cart = $store_cart->paypal_cart();
        $context = $this->context_builder->build_coupon_context($issue_type, $code, $store_cart, $wc_coupon, $config['context_builders'], $extra_context);
        // Build user message with context interpolation.
        $user_message = $this->build_user_message($config['user_message'], $code, $context, $store_cart->currency());
        $resolutions = $this->resolution_builder->build_resolution_options($issue_type, $config['resolutions'], $code, $context, $store_cart->currency());
        $resolutions = $this->apply_resolutions_filter($resolutions, $issue_type, $code, $wc_coupon, $paypal_cart, $context);
        /**
         * Filters the user-facing message for a coupon issue.
         *
         * Allows coupon plugins to customize the user message for the AI agent.
         *
         * @since 1.0.0
         * @param string         $issue_type The issue type (e.g., 'COUPON_EXPIRED').
         * @param string         $code       The coupon code.
         * @param WC_Coupon|null $wc_coupon  The WC_Coupon object (null if doesn't exist).
         * @param PayPalCart     $cart       The cart context.
         * @param array          $context    The validation context data.
         *
         * @param string         $message    The user message.
         * @return string Modified user message.
         */
        $user_message = apply_filters('woocommerce_paypal_payments_store_sync_coupon_validation_user_message', $user_message, $issue_type, $code, $wc_coupon, $paypal_cart, $context);
        $issue = ValidationIssue::create_coupon_invalid($config['message'])->user_message($user_message)->for_field($field)->add_resolution($resolutions);
        $issue_context = $this->context_builder->build_coupon_issue_context($issue_type, $code, $context);
        if ($issue_context) {
            $issue->add_context($issue_context);
        }
        return $issue;
    }
    /**
     * Builds user message with context interpolation.
     */
    private function build_user_message(string $template, string $code, array $context, string $currency): string
    {
        $placeholder_count = substr_count($template, '%s');
        if ($placeholder_count === 1) {
            return sprintf($template, $code);
        }
        if ($placeholder_count === 2) {
            $second = '';
            if (isset($context['minimum_required'])) {
                $second = Money::create($context['minimum_required'], $currency)->to_price();
            } elseif (isset($context['maximum_allowed'])) {
                $second = Money::create($context['maximum_allowed'], $currency)->to_price();
            }
            return sprintf($template, $code, $second);
        }
        if ($placeholder_count === 3) {
            return sprintf($template, $code, Money::create($context['minimum_required'] ?? 0, $currency)->to_price(), Money::create($context['current_subtotal'] ?? 0, $currency)->to_price());
        }
        return sprintf($template, $code);
    }
    /**
     * Applies resolutions enrichment filter.
     */
    private function apply_resolutions_filter(array $resolutions, string $issue_type, string $code, ?WC_Coupon $wc_coupon, PayPalCart $cart, array $context): array
    {
        /**
         * Filters the resolution options for a coupon issue.
         *
         * Allows coupon plugins to add or modify resolution options for the
         * AI agent.
         *
         * @since 1.0.0
         * @param string         $issue_type  The issue type (e.g., 'COUPON_EXPIRED').
         * @param string         $code        The coupon code.
         * @param WC_Coupon|null $wc_coupon   The WC_Coupon object (null if doesn't exist).
         * @param PayPalCart     $cart        The cart context.
         * @param array          $context     The validation context data.
         *
         * @param array          $resolutions The resolution options array.
         * @return array Modified resolution options array.
         */
        return apply_filters('woocommerce_paypal_payments_store_sync_coupon_validation_resolutions', $resolutions, $issue_type, $code, $wc_coupon, $cart, $context);
    }
}
