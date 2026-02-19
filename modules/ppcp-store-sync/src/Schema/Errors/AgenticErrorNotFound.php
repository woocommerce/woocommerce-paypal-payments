<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema\Errors;

class AgenticErrorNotFound extends AgenticError {
	protected const ERROR_NAME  = 'NOT_FOUND';
	protected const STATUS_CODE = 404;
}
