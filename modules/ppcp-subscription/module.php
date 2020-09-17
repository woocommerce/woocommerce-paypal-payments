<?php
/**
 * The module.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new SubscriptionModule();
};
