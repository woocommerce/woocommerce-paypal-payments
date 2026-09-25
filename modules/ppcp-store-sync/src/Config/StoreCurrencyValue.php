<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Config;

class StoreCurrencyValue
{
    public function value(): string
    {
        return get_woocommerce_currency();
    }
}
