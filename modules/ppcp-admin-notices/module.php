<?php
/**
 * The admin notice module.
 *
 * @package Inpsyde\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\AdminNotices;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new AdminNotices();
};
