<?php
/**
 * The button module.
 *
 * @package Inpsyde\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new ButtonModule();
};
