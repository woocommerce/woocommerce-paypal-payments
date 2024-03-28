<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module;

/**
 * @package WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module
 */
interface Module
{

    /**
     * Unique identifier for your Module.
     *
     * @return string
     */
    public function id(): string;

}
