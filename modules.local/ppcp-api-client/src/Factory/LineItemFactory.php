<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\LineItem;

class LineItemFactory
{

    public function fromWoocommerceLineItem(array $lineItem, string $currencyCode) : ?LineItem {
        if (! $this->validateWoocommerceLineItem($lineItem)) {
            return null;
        }
        $product = $lineItem['data'];
        /**
         * @var \WC_Product $product
         */
        $data = (object) [
            'reference_id' => $product->get_id(),
            'quantity' => $lineItem['quantity'],
            'description' => $product->get_name(),
            'currency_code' => $currencyCode,
            'total_amount' =>  $lineItem['line_subtotal'],
        ];

        return new LineItem($data);
    }

    private function validateWoocommerceLineItem(array $lineItem) : bool {

        $validate = [
            'data' => function($value) : bool {
                return is_a($value, \WC_Product::class);
            },
            'quantity' => 'is_numeric',
            'line_subtotal' => 'is_numeric',
        ];

        foreach ($validate as $key => $validator) {
            if (! isset($lineItem[$key]) || ! $validator($lineItem[$key])) {
                return false;
            }
        }
        return true;
    }
}