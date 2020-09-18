<?php
/**
 * The api client module.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new ApiModule();
};
