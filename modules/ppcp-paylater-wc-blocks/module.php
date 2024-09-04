<?php
/**
 * The Pay Later WooCommerce Blocks module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

return static function (): PayLaterWCBlocksModule {
	return new PayLaterWCBlocksModule();
};
