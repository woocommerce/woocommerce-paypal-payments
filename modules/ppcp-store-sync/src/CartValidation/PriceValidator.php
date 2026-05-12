<?php

/**
 * Price Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\PricingErrorContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
class PriceValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    private ProductManager $product_manager;
    public function __construct(ProductManager $product_manager)
    {
        $this->product_manager = $product_manager;
    }
    public function validate(StorePayPalCart $store_cart): ?array
    {
        // Skip validation if the cart contains an inventory issue.
        if ($store_cart->validation()->has_issue_with_code(ErrorCode::INVENTORY_ISSUE)) {
            return null;
        }
        $paypal_cart = $store_cart->paypal_cart();
        $currency = $store_cart->currency();
        $issues = array();
        foreach ($paypal_cart->items() as $key => $item) {
            $issue = $this->validate_price_matches_store($key, $item, $currency);
            if ($issue) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }
    private function validate_price_matches_store(int $key, CartItem $item, string $currency): ?ValidationIssue
    {
        $field = "items[{$key}]";
        $product = $this->product_manager->find_product($item);
        if (!$product) {
            return null;
        }
        $cart_price = $item->price();
        if (!$cart_price) {
            return null;
        }
        $store_price = (float) $product->get_price();
        if (abs($cart_price->value() - $store_price) > 0.01) {
            return $this->create_price_mismatch_issue($product, $cart_price, $store_price, $field, $currency);
        }
        return null;
    }
    private function create_price_mismatch_issue(WC_Product $product, Money $cart_price, float $store_price, string $field, string $currency): ValidationIssue
    {
        $price_difference = $store_price - $cart_price->value();
        $is_increase = $price_difference > 0;
        return ValidationIssue::create_price_mismatch(sprintf("Price mismatch for '%s': cart price is %s but store price is %s", $product->get_name(), $cart_price->value(), $store_price))->user_message(sprintf('The price of %s has %s from %s to %s.', $product->get_name(), $is_increase ? 'increased' : 'decreased', Money::create((string) $cart_price->value(), $currency)->to_price(), Money::create((string) $store_price, $currency)->to_price()))->for_field($field)->add_context($this->build_mismatch_context($cart_price, $store_price, $price_difference, $is_increase))->add_resolution($this->build_resolution_options($cart_price, $store_price, $price_difference, $is_increase, $currency));
    }
    private function build_mismatch_context(Money $cart_price, float $store_price, float $price_difference, bool $is_increase): PricingErrorContext
    {
        $context = PricingErrorContext::create_price_mismatch()->original_price(Money::create($cart_price->value() ?? 0.0)->to_decimal())->current_price(Money::create($store_price)->to_decimal())->currency_code($cart_price->currency_code() ?? '');
        if ($is_increase) {
            $context->price_increase(Money::create($price_difference)->to_decimal());
        } else {
            $context->price_decrease(Money::create(abs($price_difference))->to_decimal());
        }
        return $context;
    }
    private function build_resolution_options(Money $cart_price, float $store_price, float $price_difference, bool $is_increase, string $currency): array
    {
        $formatted_difference = sprintf('%s%s', $is_increase ? '+' : '-', Money::create((string) abs($price_difference), $currency)->to_price());
        $formatted_removal = sprintf('-%s', Money::create((string) $cart_price->value(), $currency)->to_price());
        return array(ResolutionOption::create_accept_new_price()->label(sprintf('Continue with %s', Money::create((string) $store_price, $currency)->to_price()))->priority(Priority::HIGH)->set_meta('cost_impact', $formatted_difference), ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::MEDIUM)->set_meta('cost_impact', $formatted_removal));
    }
}
