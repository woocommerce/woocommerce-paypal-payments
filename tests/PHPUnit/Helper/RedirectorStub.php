<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Helper;

use WooCommerce\PayPalCommerce\Http\RedirectorInterface;

class RedirectorStub implements RedirectorInterface
{
	public function redirect(string $location): void
	{
		throw new StubRedirectionException($location);
	}
}
