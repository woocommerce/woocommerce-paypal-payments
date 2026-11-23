<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema\Errors;

class AgenticErrorNotFound extends \WooCommerce\PayPalCommerce\StoreSync\Schema\Errors\AgenticError
{
    protected const ERROR_NAME = 'NOT_FOUND';
    protected const STATUS_CODE = 404;
}
