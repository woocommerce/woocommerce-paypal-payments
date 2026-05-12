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
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\DataErrorContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
class ProductValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    private ProductManager $product_manager;
    private IngestionConfiguration $configuration;
    public function __construct(ProductManager $product_manager, IngestionConfiguration $configuration)
    {
        $this->product_manager = $product_manager;
        $this->configuration = $configuration;
    }
    public function validate(StorePayPalCart $store_cart): ?array
    {
        // Skip validation if the cart already annotates an inventory issue.
        if ($store_cart->validation()->has_issue_with_code(ErrorCode::INVENTORY_ISSUE)) {
            return null;
        }
        $paypal_cart = $store_cart->paypal_cart();
        $issues = array();
        foreach ($paypal_cart->items() as $key => $item) {
            $issue = $this->validate_product($key, $item);
            if ($issue) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }
    private function validate_product(int $key, CartItem $item): ?ValidationIssue
    {
        $variant_id = $item->variant_id();
        $item_id = $item->item_id();
        $identifier = $variant_id ?? $item_id ?? 'unknown';
        $field = "items[{$key}]";
        $product = $this->product_manager->find_product($item);
        if (!$product) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' not found in WooCommerce catalog")->user_message("'{$item->name()}' not found in WooCommerce catalog")->for_field($field)->add_context(DataErrorContext::create_item_not_found())->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::HIGH));
        }
        if (!$product->is_purchasable()) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not available for purchase")->user_message("'{$item->name()}' cannot be purchased at this time")->for_field($field)->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::HIGH))->add_resolution(ResolutionOption::create_suggest_alternative());
        }
        $filter_args = $this->configuration->get_valid_product_filters();
        $support_downloads = (bool) ($filter_args['downloadable'] ?? \false);
        $valid_status = (array) ($filter_args['status'] ?? array());
        $valid_types = (array) ($filter_args['type'] ?? array());
        if (!$support_downloads && $product->is_downloadable()) {
            return ValidationIssue::create_invalid_product("Downloadable product '{$identifier}' is not supported")->user_message("'{$item->name()}' cannot be purchased at this time")->for_field($field);
        }
        if (!$product->is_type($valid_types)) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not supported (unsupported product type)")->user_message("'{$item->name()}' cannot be purchased at this time")->for_field($field);
        }
        if (!in_array($product->get_status(), $valid_status, \true)) {
            return ValidationIssue::create_invalid_product("Product '{$identifier}' is not supported (product has an unsupported status)")->user_message("'{$item->name()}' cannot be purchased at this time")->for_field($field);
        }
        return null;
    }
}
