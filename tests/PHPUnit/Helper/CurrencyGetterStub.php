<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;

class CurrencyGetterStub extends CurrencyGetter
{
	private string $currency;

	public function __construct(string $currency = 'EUR')
	{
		$this->currency = $currency;
	}

	public function get(): string
	{
		return $this->currency;
	}
}
