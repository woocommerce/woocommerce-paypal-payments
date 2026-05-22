<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextInventoryIssue;
/**
 * Context class for inventory-related validation issues.
 *
 * All props are optional and included in to_array() only when set.
 */
class InventoryIssueContext extends \WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext
{
    public static function create_item_out_of_stock(): self
    {
        return new self(ContextInventoryIssue::ITEM_OUT_OF_STOCK);
    }
    public static function create_insufficient_inventory(): self
    {
        return new self(ContextInventoryIssue::INSUFFICIENT_INVENTORY);
    }
    public static function create_back_ordered(): self
    {
        return new self(ContextInventoryIssue::BACK_ORDERED);
    }
    public static function create_pre_order_only(): self
    {
        return new self(ContextInventoryIssue::PRE_ORDER_ONLY);
    }
    public static function create_item_discontinued(): self
    {
        return new self(ContextInventoryIssue::ITEM_DISCONTINUED);
    }
    public static function create_low_stock_warning(): self
    {
        return new self(ContextInventoryIssue::LOW_STOCK_WARNING);
    }
    public static function create_inventory_reserved(): self
    {
        return new self(ContextInventoryIssue::INVENTORY_RESERVED);
    }
    public static function create_seasonal_unavailable(): self
    {
        return new self(ContextInventoryIssue::SEASONAL_UNAVAILABLE);
    }
    public static function create_variant_not_available(): self
    {
        return new self(ContextInventoryIssue::VARIANT_NOT_AVAILABLE);
    }
    public static function create_custom_option_unavailable(): self
    {
        return new self(ContextInventoryIssue::CUSTOM_OPTION_UNAVAILABLE);
    }
    private ?string $item_id = null;
    private ?string $variant_id = null;
    private ?int $available_quantity = null;
    private ?int $requested_quantity = null;
    private ?int $reserved_quantity = null;
    private ?string $restock_date = null;
    private ?string $estimated_ship_date = null;
    private ?int $back_order_limit = null;
    private ?int $current_back_orders = null;
    private ?string $discontinuation_date = null;
    private ?array $suggested_alternatives = null;
    private ?bool $upgrade_available = null;
    private ?string $seasonal_start_date = null;
    private ?string $last_sold = null;
    /**
     * Product item identifier.
     */
    public function item_id(?string $item_id): self
    {
        $this->item_id = $item_id;
        return $this;
    }
    /**
     * Product variant identifier if applicable.
     */
    public function variant_id(?string $variant_id): self
    {
        $this->variant_id = $variant_id;
        return $this;
    }
    /**
     * Currently available quantity.
     */
    public function available_quantity(?int $available_quantity): self
    {
        if ($available_quantity !== null && $available_quantity >= 0) {
            $this->available_quantity = $available_quantity;
        }
        return $this;
    }
    /**
     * Requested quantity.
     */
    public function requested_quantity(?int $requested_quantity): self
    {
        if ($requested_quantity !== null && $requested_quantity >= 1) {
            $this->requested_quantity = $requested_quantity;
        }
        return $this;
    }
    /**
     * Quantity reserved for other transactions.
     */
    public function reserved_quantity(?int $reserved_quantity): self
    {
        if ($reserved_quantity !== null && $reserved_quantity >= 0) {
            $this->reserved_quantity = $reserved_quantity;
        }
        return $this;
    }
    /**
     * Expected restock date.
     */
    public function restock_date(?int $restock_date): self
    {
        $this->restock_date = $this->format_date_time($restock_date);
        return $this;
    }
    /**
     * Estimated shipping date for back-orders.
     */
    public function estimated_ship_date(?int $estimated_ship_date): self
    {
        $this->estimated_ship_date = $this->format_date_time($estimated_ship_date);
        return $this;
    }
    /**
     * Maximum allowed back-order quantity.
     */
    public function back_order_limit(?int $back_order_limit): self
    {
        if ($back_order_limit !== null && $back_order_limit >= 0) {
            $this->back_order_limit = $back_order_limit;
        }
        return $this;
    }
    /**
     * Current number of back-orders.
     */
    public function current_back_orders(?int $current_back_orders): self
    {
        if ($current_back_orders !== null && $current_back_orders >= 0) {
            $this->current_back_orders = $current_back_orders;
        }
        return $this;
    }
    /**
     * Date product was discontinued.
     */
    public function discontinuation_date(?int $discontinuation_date): self
    {
        $this->discontinuation_date = $this->format_date_time($discontinuation_date);
        return $this;
    }
    /**
     * Alternative product IDs.
     */
    public function suggested_alternatives(?array $suggested_alternatives): self
    {
        $this->suggested_alternatives = $this->sanitize_string_array($suggested_alternatives);
        return $this;
    }
    /**
     * Whether newer version is available.
     */
    public function upgrade_available(?bool $upgrade_available): self
    {
        $this->upgrade_available = $upgrade_available;
        return $this;
    }
    /**
     * When seasonal product becomes available.
     */
    public function seasonal_start_date(?int $seasonal_start_date): self
    {
        $this->seasonal_start_date = $this->format_date_time($seasonal_start_date);
        return $this;
    }
    /**
     * When item was last sold.
     */
    public function last_sold(?int $last_sold): self
    {
        $this->last_sold = $this->format_date_time($last_sold);
        return $this;
    }
    public function to_array(): array
    {
        $data = array('specific_issue' => $this->specific_issue);
        if ($this->item_id !== null) {
            $data['item_id'] = $this->item_id;
        }
        if ($this->variant_id !== null) {
            $data['variant_id'] = $this->variant_id;
        }
        if ($this->available_quantity !== null) {
            $data['available_quantity'] = $this->available_quantity;
        }
        if ($this->requested_quantity !== null) {
            $data['requested_quantity'] = $this->requested_quantity;
        }
        if ($this->reserved_quantity !== null) {
            $data['reserved_quantity'] = $this->reserved_quantity;
        }
        if ($this->restock_date !== null) {
            $data['restock_date'] = $this->restock_date;
        }
        if ($this->estimated_ship_date !== null) {
            $data['estimated_ship_date'] = $this->estimated_ship_date;
        }
        if ($this->back_order_limit !== null) {
            $data['back_order_limit'] = $this->back_order_limit;
        }
        if ($this->current_back_orders !== null) {
            $data['current_back_orders'] = $this->current_back_orders;
        }
        if ($this->discontinuation_date !== null) {
            $data['discontinuation_date'] = $this->discontinuation_date;
        }
        if ($this->suggested_alternatives !== null) {
            $data['suggested_alternatives'] = $this->suggested_alternatives;
        }
        if ($this->upgrade_available !== null) {
            $data['upgrade_available'] = $this->upgrade_available;
        }
        if ($this->seasonal_start_date !== null) {
            $data['seasonal_start_date'] = $this->seasonal_start_date;
        }
        if ($this->last_sold !== null) {
            $data['last_sold'] = $this->last_sold;
        }
        return $data;
    }
}
