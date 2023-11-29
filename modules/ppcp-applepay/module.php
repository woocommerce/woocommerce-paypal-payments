<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new ApplepayModule();
};
