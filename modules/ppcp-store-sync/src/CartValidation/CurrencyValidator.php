<?php

/**
 * Currency Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
class CurrencyValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    public function validate(StorePayPalCart $store_cart): array
    {
        $currency_issue = $this->validate_currency($store_cart);
        if ($currency_issue) {
            return array($currency_issue);
        }
        return array();
    }
    private function validate_currency(StorePayPalCart $store_cart): ?ValidationIssue
    {
        $mismatch = array_filter($store_cart->cart_items(), static fn(StoreCartItem $item) => !$item->is_currency_correct());
        if (empty($mismatch)) {
            return null;
        }
        $field = reset($mismatch);
        return ValidationIssue::create_currency_mismatch(sprintf('Cart currency %s does not match store currency %s', $field->assumed_currency(), $store_cart->currency()))->user_message(sprintf('This store only accepts payments in %s.', $store_cart->currency()))->for_field($field->field_path('price.currency_code'))->add_resolution(ResolutionOption::create_use_different_currency()->label(sprintf('Change to %s', $store_cart->currency()))->set_meta('expected_currency', $store_cart->currency()));
    }
}
