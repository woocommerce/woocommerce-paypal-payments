<?php
/**
 * The api client module.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

return function (): ApiModule {
	return new ApiModule();
};
