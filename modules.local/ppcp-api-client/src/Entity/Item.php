<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class Item
{

    const PHYSICAL_GOODS = 'PHYSICAL_GOODS';
    const DIGITAL_GOODS = 'DIGITAL_GOODS';
    const VALID_CATEGORIES = [
        self::PHYSICAL_GOODS,
        self::DIGITAL_GOODS,
    ];
    private $name;
    private $unitAmount;
    private $quantity;
    private $description;
    private $tax;
    private $sku;
    private $category;

    public function __construct(
        string $name,
        Money $unitAmount,
        int $quantity,
        string $description = '',
        Money $tax = null,
        string $sku = '',
        string $category = 'PHYSICAL_GOODS'
    ) {
        $this->name = $name;
        $this->unitAmount = $unitAmount;
        $this->quantity = $quantity;
        $this->description = $description;
        $this->tax = $tax;
        $this->sku = $sku;
        $this->category = ($category === self::DIGITAL_GOODS) ? self::DIGITAL_GOODS : self::PHYSICAL_GOODS;
    }

    public function name() : string {
        return $this->name;
    }

    public function unitAmount() : Money {
        return $this->unitAmount;
    }

    public function quantity() : int {
        return $this->quantity;
    }

    public function description() : string {
        return $this->description;
    }

    public function tax() : ?Money {
        return $this->tax;
    }

    public function sku() : string {
        return $this->sku;
    }

    public function category() : string {
        return $this->category;
    }

    public function toArray() : array {
        $item = [
            'name' => $this->name(),
            'unit_amount' => $this->unitAmount()->toArray(),
            'quantity' => $this->quantity(),
            'description' => $this->description(),
            'sku' => $this->sku(),
            'category' => $this->category(),
        ];

        if ($this->tax()) {
            $item['tax'] = $this->tax()->toArray();
        }

        return $item;
    }
}