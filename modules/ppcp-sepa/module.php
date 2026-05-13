<?php
/**
 * The SEPA module.
 *
 * @package WooCommerce\PayPalCommerce\SEPA
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SEPA;

return static function (): SEPAModule {
	return new SEPAModule();
};
