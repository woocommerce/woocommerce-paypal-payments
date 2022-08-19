<?php
/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new OrderTrackingModule();
};
