<?php

/**
 * Currency Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
class CurrencyValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    private StoreCurrencyValue $store_currency;
    public function __construct(StoreCurrencyValue $store_currency)
    {
        $this->store_currency = $store_currency;
    }
    public function validate(PayPalCart $cart): array
    {
        $store_currency = $this->store_currency->value();
        $cart_currencies = $this->extract_cart_currencies($cart);
        if (empty($cart_currencies)) {
            return array();
        }
        $consistency_issue = $this->validate_consistent_currency($cart_currencies, $store_currency);
        if ($consistency_issue) {
            return array($consistency_issue);
        }
        $store_issue = $this->validate_store_currency($cart_currencies[0]['currency'], $cart_currencies[0]['index'], $store_currency);
        if ($store_issue) {
            return array($store_issue);
        }
        return array();
    }
    private function extract_cart_currencies(PayPalCart $cart): array
    {
        return array_values(array_filter(array_map(fn($index) => $this->extract_currency_at_index($cart, $index), array_keys($cart->items()))));
    }
    private function extract_currency_at_index(PayPalCart $cart, int $index): ?array
    {
        $item = $cart->items()[$index];
        $price = $item->price();
        if (!$price || !$price->currency_code()) {
            return null;
        }
        return array('index' => $index, 'currency' => $price->currency_code());
    }
    private function validate_consistent_currency(array $currencies, string $store_currency): ?ValidationIssue
    {
        $unique_currencies = array_unique(array_column($currencies, 'currency'));
        if (count($unique_currencies) === 1) {
            return null;
        }
        $reference = $currencies[0];
        $mismatch = current(array_filter($currencies, fn($item) => $item['currency'] !== $reference['currency']));
        return ValidationIssue::create_currency_mismatch(sprintf('Mixed currencies detected: item %d has currency %s, expected %s', $mismatch['index'], $mismatch['currency'], $reference['currency']))->user_message('All items in the cart must use the same currency.')->for_field("items[{$mismatch['index']}].price.currency_code")->add_resolution(ResolutionOption::create_use_different_currency()->label(sprintf('Set all items to %s', $store_currency))->set_meta('expected_currency', $store_currency)->priority(Priority::HIGH))->add_resolution(ResolutionOption::create_remove_item()->label('Remove from cart')->priority(Priority::LOW)->set_meta('item_index', $mismatch['index']));
    }
    private function validate_store_currency(string $cart_currency, int $item_index, string $store_currency): ?ValidationIssue
    {
        if ($cart_currency !== $store_currency) {
            return ValidationIssue::create_currency_mismatch(sprintf('Cart currency %s does not match store currency %s', $cart_currency, $store_currency))->user_message(sprintf('This store only accepts payments in %s.', $store_currency))->for_field("items[{$item_index}].price.currency_code")->add_resolution(ResolutionOption::create_use_different_currency()->label(sprintf('Change to %s', $store_currency))->set_meta('expected_currency', $store_currency));
        }
        return null;
    }
}
