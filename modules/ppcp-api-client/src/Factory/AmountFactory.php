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
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money as StoreApiMoney;
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
        $item_total_val = (float) $cart->get_subtotal() + (float) $cart->get_fee_total();
        $shipping_val = (float) $cart->get_shipping_total();
        $taxes_val = (float) $cart->get_total_tax();
        $discount_val = (float) $cart->get_discount_total();
        $discount_val += $this->extra_discount('woocommerce_paypal_payments_cart_extra_discount', $cart);
        $item_total = new Money($item_total_val, $this->currency->get());
        $shipping = new Money($shipping_val, $this->currency->get());
        $taxes = new Money($taxes_val, $this->currency->get());
        $discount = null;
        if ($discount_val) {
            $discount = new Money($discount_val, $this->currency->get());
        }
        // Derive the total from breakdown components in integer cents rather than
        // using get_total(), which can diverge from the component sum by ±$0.01
        // due to WooCommerce per-item tax rounding. PayPal requires amount.value to
        // exactly equal the sum of its breakdown fields or it rejects the PATCH.
        // Formatting through a string avoids floating-point representation issues
        // when converting the integer-cent sum back to a decimal (e.g. 1001/100).
        $total_cents = (int) round($item_total_val * 100) + (int) round($shipping_val * 100) + (int) round($taxes_val * 100) - (int) round($discount_val * 100);
        $total_str = number_format($total_cents / 100, 2, '.', '');
        $total = new Money((float) $total_str, $this->currency->get());
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
        return new Amount($total, $breakdown);
    }
    /**
     *  Returns an Amount object based off a WooCommerce cart object from the Store API.
     */
    public function from_store_api_cart(CartTotals $cart_totals): Amount
    {
        // Store API values are in integer minor units (e.g. cents), so integer
        // arithmetic here is exact. Fees are included in items to match
        // from_wc_cart() and to avoid a breakdown mismatch when fees are present.
        // Total is derived from the breakdown sum rather than total_price() so
        // PayPal's amount.value === sum(breakdown) invariant always holds.
        $items_minor = (int) $cart_totals->total_items()->value() + (int) $cart_totals->total_fees()->value();
        $shipping_minor = (int) $cart_totals->total_shipping()->value();
        $tax_minor = (int) $cart_totals->total_tax()->value();
        $discount_minor = (int) $cart_totals->total_discount()->value();
        /**
         * Some plugins (e.g. WooCommerce Gift Cards) reduce cart->total via WC_Cart::set_total()
         * without registering a coupon or fee. Allow them to contribute their discount amount here.
         * The value must be an integer in the cart currency's minor unit (e.g. cents for USD).
         */
        $discount_minor += max(0, (int) apply_filters('woocommerce_paypal_payments_store_api_cart_extra_discount', 0, $cart_totals));
        $total_minor = $items_minor + $shipping_minor + $tax_minor - $discount_minor;
        $currency = $cart_totals->total_price()->currency_code();
        $minor_unit = $cart_totals->total_price()->currency_minor_unit();
        $make = static function (int $minor) use ($currency, $minor_unit): Money {
            return (new StoreApiMoney((string) $minor, $currency, $minor_unit))->to_paypal();
        };
        return new Amount($make($total_minor), new AmountBreakdown($make($items_minor), $make($shipping_minor), $make($tax_minor), null, null, null, $discount_minor > 0 ? $make($discount_minor) : null));
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
        $items_discount = $this->discounts_from_items($items);
        $discount_value = array_sum(array(
            (float) $order->get_total_discount(),
            // Only coupons.
            $items_discount,
        ));
        $discount_value += $this->extra_discount('woocommerce_paypal_payments_order_extra_discount', $order);
        $discount = null;
        if ($discount_value) {
            $discount = new Money((float) $discount_value, $currency);
        }
        // Negative items (e.g. a negative fee) are surfaced as `discount`, and the
        // negative-amount items are filtered out by PurchaseUnitFactory before being
        // sent to PayPal. Add them back here so item_total reflects the positive items
        // actually sent and the negative fee is not counted twice (once in get_total_fees()
        // and again in discount), which would otherwise undercharge the PayPal total.
        $item_total_val = (float) $order->get_subtotal() + (float) $order->get_total_fees() + $items_discount;
        $shipping_val = (float) $order->get_shipping_total();
        $taxes_val = (float) $order->get_total_tax();
        $item_total = new Money($item_total_val, $currency);
        $shipping = new Money($shipping_val, $currency);
        // Free trial orders charge a fixed $1.00 regardless of cart contents —
        // preserve that override. For all other orders use get_total() as the
        // authoritative amount and adjust the tax breakdown by any rounding delta
        // (typically ±1 cent from inclusive-tax rounding) so that PayPal's
        // invariant — amount.value === sum(breakdown) — always holds while the
        // total still matches what WooCommerce stored on the order.
        if ((in_array($order->get_payment_method(), array(CreditCardGateway::ID, CardButtonGateway::ID), \true) || PayPalGateway::ID === $order->get_payment_method() && 'card' === $order->get_meta(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY)) && $this->is_free_trial_order($order)) {
            $taxes = new Money($taxes_val, $currency);
            $total = new Money(1.0, $currency);
        } else {
            $wc_total = (float) $order->get_total();
            $wc_total_cents = (int) round($wc_total * 100);
            $component_total_cents = (int) round($item_total_val * 100) + (int) round($shipping_val * 100) + (int) round($taxes_val * 100) - (int) round($discount_value * 100);
            $taxes_cents = (int) round($taxes_val * 100) + ($wc_total_cents - $component_total_cents);
            $taxes = new Money($taxes_cents / 100, $currency);
            $total = new Money($wc_total, $currency);
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
        return new Amount($total, $breakdown);
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
     * Returns any extra discount contributed via a filter hook.
     *
     * Some plugins (e.g. WooCommerce Gift Cards) reduce the WC total directly via
     * WC_Cart::set_total() / WC_Order::set_total() without registering a coupon or fee,
     * making their discount invisible to the standard breakdown getters. This method
     * applies the given filter so those plugins can surface their amount here.
     *
     * @param string $filter_name The filter hook name.
     * @param mixed  $context     The cart or order passed as context to the filter.
     * @return float
     */
    private function extra_discount(string $filter_name, $context): float
    {
        /**
         * Filters extra discount amounts not captured by standard WC discount/fee getters.
         */
        return max(0.0, (float) apply_filters($filter_name, 0.0, $context));
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
