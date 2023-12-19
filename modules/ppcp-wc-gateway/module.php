<?php
/**
 * The module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

return static function (): WCGatewayModule {
	return new WCGatewayModule();
};
