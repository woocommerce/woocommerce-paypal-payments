<?php
/**
 * The module.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new WcGatewayModule();
};
