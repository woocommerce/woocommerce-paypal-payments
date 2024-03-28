<?php
/**
 * The save payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

return static function (): SavePaymentMethodsModule {
	return new SavePaymentMethodsModule();
};
