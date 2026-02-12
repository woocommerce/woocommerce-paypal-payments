<?php

/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce;

return static function (): \WooCommerce\PayPalCommerce\AgenticCommerce\AgenticCommerceModule {
    return new \WooCommerce\PayPalCommerce\AgenticCommerce\AgenticCommerceModule();
};
