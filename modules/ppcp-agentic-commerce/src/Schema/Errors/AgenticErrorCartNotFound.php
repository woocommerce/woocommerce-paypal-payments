<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors;

class AgenticErrorCartNotFound extends AgenticError {
	protected const ERROR_NAME  = 'CART_NOT_FOUND';
	protected const STATUS_CODE = 404;
}
