<?php
/**
 * The module.
 *
 * @package Inpsyde\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Subscription;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new SubscriptionModule();
};
