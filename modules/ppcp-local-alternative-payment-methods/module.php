<?php
/**
 * The local alternative payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new LocalAlternativePaymentMethodsModule();
};
