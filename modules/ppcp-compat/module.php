<?php
/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new CompatModule();
};
