<?php
/**
 * The Pay Later WooCommerce Blocks module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new PayLaterWCBlocksModule();
};
