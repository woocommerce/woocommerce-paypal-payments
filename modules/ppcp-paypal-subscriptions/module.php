<?php
/**
 * The PayPalSubscriptions module.
 *
 * @package WooCommerce\PayPalCommerce\PayPalSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new PayPalSubscriptionsModule();
};
