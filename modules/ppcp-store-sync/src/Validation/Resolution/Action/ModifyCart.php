<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\Action;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ResolutionAction;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;

class ModifyCart extends ResolutionOption {
	protected const RESOLUTION_ACTION = ResolutionAction::MODIFY_CART;
}
