<?php
/**
 * The Not Found Exception for the Settings Container.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {


}
