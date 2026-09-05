<?php

/**
 * The session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session;

return function (): \WooCommerce\PayPalCommerce\Session\SessionModule {
    return new \WooCommerce\PayPalCommerce\Session\SessionModule();
};
