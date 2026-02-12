<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors;

class AgenticErrorNotFound extends \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors\AgenticError
{
    protected const ERROR_NAME = 'NOT_FOUND';
    protected const STATUS_CODE = 404;
}
