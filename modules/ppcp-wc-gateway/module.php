<?php
/**
 * The module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new WcGatewayModule();
};
