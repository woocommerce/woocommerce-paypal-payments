<?php
/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

return array(
	'vaulting.payment-tokens-renderer' => static function (): PaymentTokensRendered {
		return new PaymentTokensRendered();
	},
);
