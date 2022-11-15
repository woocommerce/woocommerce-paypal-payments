<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\Exception;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleAwareInterface;
use Throwable;

/**
 * Represents an exception that is thrown in relation to a module.
 *
 * @since 0.2
 */
interface ModuleExceptionInterface extends
    Throwable,
    ModuleAwareInterface
{
}
