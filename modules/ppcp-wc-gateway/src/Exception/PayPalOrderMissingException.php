<?php
/**
 * Thrown when there is no PayPal order during WC order processing.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Exception;

use Exception;

/**
 * Class PayPalOrderMissingException
 */
class PayPalOrderMissingException extends Exception {
}
