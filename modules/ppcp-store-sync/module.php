<?php
/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync;

return static function (): AgenticCommerceModule {
	return new AgenticCommerceModule();
};
