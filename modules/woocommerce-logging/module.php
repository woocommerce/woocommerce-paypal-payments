<?php
/**
 * The logging module.
 *
 * @package Inpsyde\WooCommerce\Logging
 */

declare(strict_types=1);

namespace Inpsyde\WooCommerce\Logging;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new WooCommerceLoggingModule();
};
