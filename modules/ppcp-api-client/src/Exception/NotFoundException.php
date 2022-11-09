<?php
/**
 * The modules Not Found exception.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Exception;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\NotFoundExceptionInterface;
use Exception;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface {


}
