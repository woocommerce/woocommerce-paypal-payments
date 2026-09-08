<?php

/**
 * Product Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
class ProductValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    private ProductFilter $product_filter;
    public function __construct(ProductFilter $product_filter)
    {
        $this->product_filter = $product_filter;
    }
    public function validate(StorePayPalCart $store_cart): ?array
    {
        // Skip validation if the cart already annotates an inventory issue.
        if ($store_cart->validation()->has_issue_with_code(ErrorCode::INVENTORY_ISSUE)) {
            return null;
        }
        return array_filter(array_map(fn(StoreCartItem $item): ?ValidationIssue => $this->validate_product($item), $store_cart->cart_items()));
    }
    private function validate_product(StoreCartItem $item): ?ValidationIssue
    {
        $identifier = $item->id();
        $field = $item->field_path();
        $product = $item->product();
        $product_name = $product->get_name();
        if (!$product->is_purchasable()) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not available for purchase")->user_message("'{$product_name}' cannot be purchased at this time")->for_field($field)->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::HIGH))->add_resolution(ResolutionOption::create_suggest_alternative());
        }
        $violation = $this->product_filter->criteria_violation($product);
        if (null !== $violation) {
            return ValidationIssue::create_invalid_product($this->criteria_message($violation, $identifier))->user_message("'{$product_name}' cannot be purchased at this time")->for_field($field);
        }
        if (!$this->product_filter->passes_exclusion_filter($product)) {
            // Additive write-through: heal the ingestion feed faster. Never release here.
            $this->product_filter->mark_processed($product);
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not supported")->user_message("'{$product_name}' cannot be purchased at this time")->for_field($field)->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::HIGH));
        }
        return null;
    }
    /**
     * Maps a coarse-criteria violation slug to its developer-facing message.
     *
     * @param string $violation  One of 'downloadable' | 'type' | 'status'.
     * @param string $identifier The cart item identifier.
     */
    private function criteria_message(string $violation, string $identifier): string
    {
        switch ($violation) {
            case 'downloadable':
                return "Downloadable product '{$identifier}' is not supported";
            case 'type':
                return "Product '{$identifier}' is not supported (unsupported product type)";
            case 'status':
            default:
                return "Product '{$identifier}' is not supported (product has an unsupported status)";
        }
    }
}
