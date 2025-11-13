<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

return static function (): FraudProtectionModule {
	return new FraudProtectionModule();
};
