<?php
/**
 * The logging module.
 *
 * @package Inpsyde\Woocommerce\Logging
 */

declare(strict_types=1);

namespace Inpsyde\Woocommerce\Logging;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new WoocommerceLoggingModule();
};
