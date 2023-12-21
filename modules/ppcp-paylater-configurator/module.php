<?php
/**
 * The Pay Later configurator module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new PayLaterConfiguratorModule();
};
