<?php

/**
 * Product Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\Priority;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\ResolutionOption;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidProduct;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;
class ProductValidator implements \WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\ValidatorInterface
{
    private ProductManager $product_manager;
    public function __construct(ProductManager $product_manager)
    {
        $this->product_manager = $product_manager;
    }
    public function validate(PayPalCart $cart)
    {
        // Skip validation if the cart already annotates an inventory issue.
        if ($cart->has_validation_issue(ErrorCode::INVENTORY_ISSUE)) {
            return null;
        }
        $issues = array();
        foreach ($cart->items() as $key => $item) {
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
            return new InvalidProduct("Product '{$identifier}' not found in WooCommerce catalog", "'{$item->name()}' not found in WooCommerce catalog", $field, '', array(), array(ResolutionOption::remove_item(Priority::HIGH)));
        }
        if (!$product->is_purchasable()) {
            return new InvalidProduct("Product '{$identifier}' is not available for purchase", "'{$item->name()}' cannot be purchased at this time", $field, '', array(), array(ResolutionOption::remove_item(Priority::HIGH), ResolutionOption::suggest_alternative()));
        }
        return null;
    }
}
