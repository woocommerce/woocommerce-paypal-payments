<?php

/**
 * The status report module.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StatusReport;

return static function (): \WooCommerce\PayPalCommerce\StatusReport\StatusReportModule {
    return new \WooCommerce\PayPalCommerce\StatusReport\StatusReportModule();
};
