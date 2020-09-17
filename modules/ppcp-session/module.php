<?php
/**
 * The session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new SessionModule();
};
