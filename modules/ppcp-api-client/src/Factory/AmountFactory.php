<?php

/**
 * The Amount factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class AmountFactory
 */
class AmountFactory
{
    use FreeTrialHandlerTrait;
    /**
     * The item factory.
     *
     * @var ItemFactory
     */
    private $item_factory;
    /**
     * The Money factory.
     *
     * @var MoneyFactory
     */
    private $money_factory;
    /**
     * The getter of the 3-letter currency code of the shop.
     *
     * @var CurrencyGetter
     */
    private CurrencyGetter $currency;
    /**
     * AmountFactory constructor.
     *
     * @param ItemFactory    $item_factory The Item factory.
     * @param MoneyFactory   $money_factory The Money factory.
     * @param CurrencyGetter $currency The getter of the 3-letter currency code of the shop.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Factory\ItemFactory $item_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\MoneyFactory $money_factory, CurrencyGetter $currency)
    {
        $this->item_factory = $item_factory;
        $this->money_factory = $money_factory;
        $this->currency = $currency;
    }
    /**
     * Returns an Amount object based off a WooCommerce cart.
     *
     * @param \WC_Cart $cart The cart.
     *
     * @return Amount
     */
    public function from_wc_cart(\WC_Cart $cart): Amount
    {
        $total = new Money((float) $cart->get_total('numeric'), $this->currency->get());
        $item_total = (float) $cart->get_subtotal() + (float) $cart->get_fee_total();
        $item_total = new Money($item_total, $this->currency->get());
        $shipping = new Money((float) $cart->get_shipping_total(), $this->currency->get());
        $taxes = new Money((float) $cart->get_total_tax(), $this->currency->get());
        $discount = null;
        if ($cart->get_discount_total()) {
            $discount = new Money((float) $cart->get_discount_total(), $this->currency->get());
        }
        $breakdown = new AmountBreakdown(
            $item_total,
            $shipping,
            $taxes,
            null,
            // insurance?
            null,
            // handling?
            null,
            // shipping discounts?
            $discount
        );
        $amount = new Amount($total, $breakdown);
        return $amount;
    }
    /**
     *  Returns an Amount object based off a WooCommerce cart object from the Store API.
     */
    public function from_store_api_cart(CartTotals $cart_totals): Amount
    {
        return new Amount($cart_totals->total_price()->to_paypal(), new AmountBreakdown($cart_totals->total_items()->to_paypal(), $cart_totals->total_shipping()->to_paypal(), $cart_totals->total_tax()->to_paypal(), null, null, null, $cart_totals->total_discount()->to_paypal()));
    }
    /**
     * Returns an Amount object based off a WooCommerce order.
     *
     * @param \WC_Order $order The order.
     *
     * @return Amount
     */
    public function from_wc_order(\WC_Order $order): Amount
    {
        $currency = $order->get_currency();
        $items = $this->item_factory->from_wc_order($order);
        $discount_value = array_sum(array(
            (float) $order->get_total_discount(),
            // Only coupons.
            $this->discounts_from_items($items),
        ));
        $discount = null;
        if ($discount_value) {
            $discount = new Money((float) $discount_value, $currency);
        }
        $total_value = (float) $order->get_total();
        if ((in_array($order->get_payment_method(), array(CreditCardGateway::ID, CardButtonGateway::ID), \true) || PayPalGateway::ID === $order->get_payment_method() && 'card' === $order->get_meta(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY)) && $this->is_free_trial_order($order)) {
            $total_value = 1.0;
        }
        $total = new Money($total_value, $currency);
        $item_total = new Money((float) $order->get_subtotal() + (float) $order->get_total_fees(), $currency);
        $shipping = new Money((float) $order->get_shipping_total(), $currency);
        $taxes = new Money((float) $order->get_total_tax(), $currency);
        $breakdown = new AmountBreakdown(
            $item_total,
            $shipping,
            $taxes,
            null,
            // insurance?
            null,
            // handling?
            null,
            // shipping discounts?
            $discount
        );
        $amount = new Amount($total, $breakdown);
        return $amount;
    }
    /**
     * Returns an Amount object based off a PayPal Response.
     *
     * @param mixed $data The JSON object.
     *
     * @return Amount|null
     */
    public function from_paypal_response($data)
    {
        if (null === $data || !$data instanceof \stdClass) {
            return null;
        }
        $money = $this->money_factory->from_paypal_response($data);
        $breakdown = isset($data->breakdown) ? $this->break_down($data->breakdown) : null;
        return new Amount($money, $breakdown);
    }
    /**
     * Returns a AmountBreakdown object based off a PayPal response.
     *
     * @param \stdClass $data The JSON object.
     *
     * @return AmountBreakdown
     * @throws RuntimeException When JSON object is malformed.
     */
    private function break_down(\stdClass $data): AmountBreakdown
    {
        /**
         * The order of the keys equals the necessary order of the constructor arguments.
         */
        $ordered_constructor_keys = array('item_total', 'shipping', 'tax_total', 'handling', 'insurance', 'shipping_discount', 'discount');
        $money = array();
        foreach ($ordered_constructor_keys as $key) {
            if (!isset($data->{$key})) {
                $money[] = null;
                continue;
            }
            $item = $data->{$key};
            if (!isset($item->value) || !is_numeric($item->value)) {
                throw new RuntimeException(sprintf('No value given for breakdown %s', $key));
            }
            if (!isset($item->currency_code)) {
                throw new RuntimeException(sprintf('No currency given for breakdown %s', $key));
            }
            $money[] = new Money((float) $item->value, $item->currency_code);
        }
        return new AmountBreakdown(...$money);
    }
    /**
     * Returns the sum of items with negative amount;
     *
     * @param Item[] $items PayPal order items.
     * @return float
     */
    private function discounts_from_items(array $items): float
    {
        $discounts = array_filter($items, function (Item $item): bool {
            return $item->unit_amount()->value() < 0;
        });
        return abs(array_sum(array_map(function (Item $item): float {
            return (float) $item->quantity() * $item->unit_amount()->value();
        }, $discounts)));
    }
}
