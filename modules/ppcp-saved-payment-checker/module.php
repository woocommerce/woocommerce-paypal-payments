<?php
/**
 * The SavedPaymentChecker module.
 *
 * @package WooCommerce\PayPalCommerce\SavedPaymentChecker
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker;

return static function (): SavedPaymentCheckerModule {
	return new SavedPaymentCheckerModule();
};
