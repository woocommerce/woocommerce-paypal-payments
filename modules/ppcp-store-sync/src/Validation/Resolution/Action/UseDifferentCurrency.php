<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\Action;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ResolutionAction;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;

class UseDifferentCurrency extends ResolutionOption {
	protected const RESOLUTION_ACTION = ResolutionAction::USE_DIFFERENT_CURRENCY;
}
