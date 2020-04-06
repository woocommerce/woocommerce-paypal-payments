<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Money;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class ItemFactory
{

    public function __construct()
    {
    }

    public function fromPayPalRequest(\stdClass $data) : Item
    {
        if (! isset($data->name)) {
            throw new RuntimeException(__("No name for item given", "woocommerce-paypal-commerce-gateway"));
        }
        if (! isset($data->quantity) || ! is_numeric($data->quantity)) {
            throw new RuntimeException(__("No quantity for item given", "woocommerce-paypal-commerce-gateway"));
        }
        if (! isset($data->unit_amount->value) || ! isset($data->unit_amount->currency_code)) {
            throw new RuntimeException(__("No money values for item given", "woocommerce-paypal-commerce-gateway"));
        }

        $unitAmount = new Money((float) $data->unit_amount->value, $data->unit_amount->currency_code);
        $description = (isset($data->description)) ? $data->description : '';
        $tax = (isset($data->tax)) ? new Money((float) $data->tax->value, $data->tax->currency_code) : null;
        $sku = (isset($data->sku)) ? $data->sku : '';
        $category = (isset($data->category)) ? $data->category : 'PHYSICAL_GOODS';

        return new Item(
            $data->name,
            $unitAmount,
            (int) $data->quantity,
            $description,
            $tax,
            $sku,
            $category
        );
    }
}