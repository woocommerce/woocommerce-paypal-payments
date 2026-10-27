<?php

/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

return static function (): \WooCommerce\PayPalCommerce\Webhooks\WebhookModule {
    return new \WooCommerce\PayPalCommerce\Webhooks\WebhookModule();
};
