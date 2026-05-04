<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\WcPaymentTokens
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

return static function (): WcPaymentTokensModule {
	return new WcPaymentTokensModule();
};
