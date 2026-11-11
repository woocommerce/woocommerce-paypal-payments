<?php

/**
 * The logging module.
 *
 * @package WooCommerce\WooCommerce\Logging
 */
declare (strict_types=1);
namespace WooCommerce\WooCommerce\Logging;

return function (): \WooCommerce\WooCommerce\Logging\WooCommerceLoggingModule {
    return new \WooCommerce\WooCommerce\Logging\WooCommerceLoggingModule();
};
