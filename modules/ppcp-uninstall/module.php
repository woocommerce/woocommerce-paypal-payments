<?php
/**
 * The uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

return function (): UninstallModule {
	return new UninstallModule();
};
