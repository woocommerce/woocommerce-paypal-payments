<?php

/**
 * Inventory Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\InventoryIssueContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
class InventoryValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    private ProductManager $product_manager;
    public function __construct(ProductManager $product_manager)
    {
        $this->product_manager = $product_manager;
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
        $product = $item->product();
        if (!$this->product_manager->is_in_stock($product)) {
            return ValidationIssue::create_item_out_of_stock('Product is no longer available')->user_message(sprintf('%s is currently out of stock.', $product->get_name()))->for_field($item->field_path())->add_context(InventoryIssueContext::create_item_out_of_stock()->item_id($item->id()))->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::HIGH))->add_resolution(ResolutionOption::create_wait_for_restock());
        }
        if (!$this->product_manager->is_in_stock($product, $item->quantity())) {
            $stock_quantity = $product->get_stock_quantity() ?? 0;
            return ValidationIssue::create_insufficient_quantity('Insufficient inventory')->user_message(sprintf('Only %d of %s available, but %d requested.', $stock_quantity, $product->get_name(), $item->quantity()))->for_field($item->field_path())->add_context(InventoryIssueContext::create_insufficient_inventory()->item_id($item->id())->available_quantity($stock_quantity)->requested_quantity($item->quantity()))->add_resolution(ResolutionOption::create_modify_cart()->label(sprintf('Reduce quantity to %d', $stock_quantity))->priority(Priority::HIGH)->set_meta('max_quantity', $stock_quantity))->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::LOW));
        }
        return null;
    }
}
