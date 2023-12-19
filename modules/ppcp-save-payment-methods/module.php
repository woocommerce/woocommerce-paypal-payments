<?php
/**
 * The save payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new SavePaymentMethodsModule();
};
