<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection;

return static function (): \WooCommerce\PayPalCommerce\FraudProtection\FraudProtectionModule {
    return new \WooCommerce\PayPalCommerce\FraudProtection\FraudProtectionModule();
};
