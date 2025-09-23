<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors;

class AgenticErrorInvalidRequest extends AgenticError {
	protected const ERROR_NAME  = 'INVALID_REQUEST';
	protected const STATUS_CODE = 400;
}

