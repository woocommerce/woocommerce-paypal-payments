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

    public function fromWcCart(\WC_Cart $cart) : array {
        $currency = get_woocommerce_currency();
        $items = array_map(
            function (array $item) use ($currency): Item {
                $product = $item['data'];
                /**
                 * @var \WC_Product $product
                 */
                $quantity = (int) $item['quantity'];

                $price = (float) wc_get_price_including_tax($product);
                $priceWithoutTax = (float) wc_get_price_excluding_tax($product);
                $priceWithoutTaxRounded = round($priceWithoutTax, 2);
                $tax = round($price - $priceWithoutTaxRounded, 2);
                $tax = new Money($tax, $currency);
                return new Item(
                    mb_substr($product->get_name(), 0, 127),
                    new Money($priceWithoutTaxRounded, $currency),
                    $quantity,
                    mb_substr($product->get_description(), 0, 127),
                    $tax,
                    $product->get_sku(),
                    ($product->is_downloadable()) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
                );
            },
            $cart->get_cart_contents()
        );
        return $items;
    }

    /**
     * @param \WC_Order $order
     * @return Item[]
     */
    public function fromWcOrder(\WC_Order $order) : array {

        return array_map(
            function (\WC_Order_Item_Product $item) use ($order): Item {
                return $this->fromWcOrderLineItem($item, $order);
            },
            $order->get_items('line_item')
        );
    }

    private function fromWcOrderLineItem(\WC_Order_Item_Product $item, \WC_Order $order) : Item {

        $currency = $order->get_currency();
        $product = $item->get_product();
        /**
         * @var \WC_Product $product
         */
        $quantity = $item->get_quantity();

        $price = (float) $order->get_item_subtotal($item, true);
        $priceWithoutTax = (float) $order->get_item_subtotal($item, false);
        $priceWithoutTaxRounded = round($priceWithoutTax, 2);
        $tax = round($price - $priceWithoutTaxRounded, 2);
        $tax = new Money($tax, $currency);
        return new Item(
            mb_substr($product->get_name(), 0, 127),
            new Money($priceWithoutTaxRounded, $currency),
            $quantity,
            mb_substr($product->get_description(), 0, 127),
            $tax,
            $product->get_sku(),
            ($product->is_downloadable()) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
        );
    }

    public function fromPayPalRequest(\stdClass $data) : Item
    {
        if (! isset($data->name)) {
            throw new RuntimeException(__("No name for item given", "woocommerce-paypal-commerce-gateway"));
        }
        if (! isset($data->quantity) || ! is_numeric($data->quantity)) {
            throw new RuntimeException(
                __("No quantity for item given", "woocommerce-paypal-commerce-gateway")
            );
        }
        if (! isset($data->unit_amount->value) || ! isset($data->unit_amount->currency_code)) {
            throw new RuntimeException(
                __("No money values for item given", "woocommerce-paypal-commerce-gateway")
            );
        }

        $unitAmount = new Money((float) $data->unit_amount->value, $data->unit_amount->currency_code);
        $description = (isset($data->description)) ? $data->description : '';
        $tax = (isset($data->tax)) ?
            new Money((float) $data->tax->value, $data->tax->currency_code)
            : null;
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
