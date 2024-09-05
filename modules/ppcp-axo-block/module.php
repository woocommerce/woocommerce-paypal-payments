<?php
/**
 * The Axo Block module.
 *
 * @package WooCommerce\PayPalCommerce\AxoBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AxoBlock;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new AxoBlockModule();
};
