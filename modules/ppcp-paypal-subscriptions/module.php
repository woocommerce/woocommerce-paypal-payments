<?php
/**
 * The PayPalSubscriptions module.
 *
 * @package WooCommerce\PayPalCommerce\PayPalSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

return static function (): PayPalSubscriptionsModule {
	return new PayPalSubscriptionsModule();
};
