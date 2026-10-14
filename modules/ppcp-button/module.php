<?php

/**
 * The button module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button;

return static function (): \WooCommerce\PayPalCommerce\Button\ButtonModule {
    return new \WooCommerce\PayPalCommerce\Button\ButtonModule();
};
