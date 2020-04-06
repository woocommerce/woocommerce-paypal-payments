<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\Amount;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Money;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class AmountFactory
{

    public function __construct()
    {
    }

    public function fromPayPalResponse(\stdClass $data) : Amount{

        if (! isset($data->value) || ! is_numeric($data->value)) {
            throw new RuntimeException(__("No value given", "woocommerce-paypal-commerce-gateway"));
        }
        if (! isset($data->currency_code)) {
            throw new RuntimeException(__("No currency given", "woocommerce-paypal-commerce-gateway"));
        }

        $money = new Money((float) $data->value, $data->currency_code);
        $breakdown = (isset($data->breakdown)) ? $this->breakdown($data->breakdown) : null;
        return new Amount($money, $breakdown);
    }

    private function breakDown(\stdClass $data) : AmountBreakdown {
        /**
         * The order of the keys equals the necessary order of the constructor arguments.
         */
        $orderedConstructorKeys = [
            'item_total',
            'shipping',
            'tax_total',
            'handling',
            'insurance',
            'shipping_discount',
            'discount',
        ];

        $money = [];
        foreach ($orderedConstructorKeys as $key) {
            if (! isset($data->{$key})) {
                $money[] = null;
                continue;
            }
            $item = $data->{$key};

            if (! isset($item->value) || ! is_numeric($item->value)) {
                throw new RuntimeException(sprintf(
                    __("No value given for breakdown %s", "woocommerce-paypal-commerce-gateway"),
                    $key
                ));
            }
            if (! isset($item->currency_code)) {
                throw new RuntimeException(sprintf(
                    __("No currency given for breakdown %s", "woocommerce-paypal-commerce-gateway"),
                    $key
                ));
            }
            $money[] = new Money((float) $item->value, $item->currency_code);
        }

        return new AmountBreakdown(...$money);
    }
}