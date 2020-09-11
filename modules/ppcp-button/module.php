<?php
/**
 * The button module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new ButtonModule();
};
