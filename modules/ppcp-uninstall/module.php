<?php

/**
 * The uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall;

return function (): \WooCommerce\PayPalCommerce\Uninstall\UninstallModule {
    return new \WooCommerce\PayPalCommerce\Uninstall\UninstallModule();
};
