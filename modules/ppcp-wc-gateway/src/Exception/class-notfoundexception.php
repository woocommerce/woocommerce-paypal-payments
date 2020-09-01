<?php
/**
 * The Not Found Exception for the Settings Container.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Exception
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {


}
