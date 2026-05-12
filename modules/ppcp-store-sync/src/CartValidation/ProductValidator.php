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
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
class ProductValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    private IngestionConfiguration $configuration;
    public function __construct(IngestionConfiguration $configuration)
    {
        $this->configuration = $configuration;
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
        $filter_args = $this->configuration->get_valid_product_filters();
        $support_downloads = (bool) ($filter_args['downloadable'] ?? \false);
        $valid_status = (array) ($filter_args['status'] ?? array());
        $valid_types = (array) ($filter_args['type'] ?? array());
        if (!$support_downloads && $product->is_downloadable()) {
            return ValidationIssue::create_invalid_product("Downloadable product '{$identifier}' is not supported")->user_message("'{$product_name}' cannot be purchased at this time")->for_field($field);
        }
        if (!$product->is_type($valid_types)) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not supported (unsupported product type)")->user_message("'{$product_name}' cannot be purchased at this time")->for_field($field);
        }
        if (!in_array($product->get_status(), $valid_status, \true)) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not supported (product has an unsupported status)")->user_message("'{$product_name}' cannot be purchased at this time")->for_field($field);
        }
        return null;
    }
}
