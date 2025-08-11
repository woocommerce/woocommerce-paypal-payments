<?php
declare(strict_types=1);

namespace Automattic\WooCommerce\Admin\Features\PaymentGatewaySuggestions;

class DefaultPaymentGateways
{
	public static function get_all(): array
	{
		return [];
	}

	public static function get_wcpay_countries(): array
	{
		return ['US', 'CA', 'GB', 'AU', 'DE', 'FR'];
	}
}
