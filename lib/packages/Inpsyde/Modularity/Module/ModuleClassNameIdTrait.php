<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module;

/**
 * Trait ModuleClassNameIdTrait
 *
 * @package WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module
 */
trait ModuleClassNameIdTrait
{

    /**
     * @return string
     * @see Module::id()
     */
    public function id(): string
    {
        return __CLASS__;
    }
}
