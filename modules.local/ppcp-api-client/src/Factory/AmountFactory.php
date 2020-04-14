<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Amount;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Money;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class AmountFactory
{

    private $itemFactory;
    public function __construct(ItemFactory $itemFactory)
    {
        $this->itemFactory = $itemFactory;
    }

    public function fromWcCart(\WC_Cart $cart) : Amount
    {
        $currency = get_woocommerce_currency();
        $total = new Money((float) $cart->get_total('numeric'), $currency);
        $itemsTotal = $cart->get_cart_contents_total() + $cart->get_discount_total();
        $itemsTotal = new Money((float) $itemsTotal, $currency);
        $shipping = new Money(
            (float) $cart->get_shipping_total() + $cart->get_shipping_tax(),
            $currency
        );

        $taxes = new Money(
            (float) $cart->get_cart_contents_tax() + (float) $cart->get_discount_tax(),
            $currency
        );

        $discount = null;
        if ($cart->get_discount_total()) {
            $discount = new Money(
                (float) $cart->get_discount_total() + $cart->get_discount_tax(),
                $currency
            );
        }
        //ToDo: Evaluate if more is needed? Fees?
        $breakdown = new AmountBreakdown(
            $itemsTotal,
            $shipping,
            $taxes,
            null, // insurance?
            null, // handling?
            null, //shipping discounts?
            $discount
        );
        $amount = new Amount(
            $total,
            $breakdown
        );
        return $amount;
    }

    public function fromWcOrder(\WC_Order $order) : Amount
    {
        $currency = $order->get_currency();
        $items = $this->itemFactory->fromWcOrder($order);
        $total = new Money((float) $order->get_total(), $currency);
        $itemsTotal = new Money((float)array_reduce(
            $items,
            function (float $total, Item $item): float {
                return $total + $item->quantity() * $item->unitAmount()->value();
            },
            0
        ), $currency);
        $shipping = new Money(
            (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(),
            $currency
        );
        $taxes = new Money((float)array_reduce(
            $items,
            function (float $total, Item $item): float {
                return $total + $item->quantity() * $item->tax()->value();
            },
            0
        ), $currency);

        $discount = null;
        if ((float) $order->get_total_discount(false)) {
            $discount = new Money(
                (float) $order->get_total_discount(false),
                $currency
            );
        }

        //ToDo: Evaluate if more is needed? Fees?
        $breakdown = new AmountBreakdown(
            $itemsTotal,
            $shipping,
            $taxes,
            null, // insurance?
            null, // handling?
            null, //shipping discounts?
            $discount
        );
        $amount = new Amount(
            $total,
            $breakdown
        );
        return $amount;
    }

    public function fromPayPalResponse(\stdClass $data) : Amount
    {
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

    private function breakDown(\stdClass $data) : AmountBreakdown
    {
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
                    // translators: %s is the current breakdown key.
                    __("No value given for breakdown %s", "woocommerce-paypal-commerce-gateway"),
                    $key
                ));
            }
            if (! isset($item->currency_code)) {
                throw new RuntimeException(sprintf(
                    // translators: %s is the current breakdown key.
                    __("No currency given for breakdown %s", "woocommerce-paypal-commerce-gateway"),
                    $key
                ));
            }
            $money[] = new Money((float) $item->value, $item->currency_code);
        }

        return new AmountBreakdown(...$money);
    }
}
