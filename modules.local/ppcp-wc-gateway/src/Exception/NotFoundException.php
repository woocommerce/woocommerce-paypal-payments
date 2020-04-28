<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{

}
