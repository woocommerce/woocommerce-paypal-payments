<?php
/**
 * The modules Not Found exception.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Exception
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {


}
