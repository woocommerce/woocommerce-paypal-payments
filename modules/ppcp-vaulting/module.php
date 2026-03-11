<?php

/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

return static function (): \WooCommerce\PayPalCommerce\Vaulting\VaultingModule {
    return new \WooCommerce\PayPalCommerce\Vaulting\VaultingModule();
};
