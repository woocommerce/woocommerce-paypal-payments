<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{

}
