<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

return static function (): ApplepayModule {
	return new ApplepayModule();
};
