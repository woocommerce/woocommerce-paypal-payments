<?php

/**
 * The uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
/**
 * Class UninstallModule
 */
class UninstallModule implements ServiceModule
{
    use ModuleClassNameIdTrait;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
}
