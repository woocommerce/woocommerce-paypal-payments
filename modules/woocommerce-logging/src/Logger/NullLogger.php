<?php

/**
 * The Null logger is used, when logging is disabled. It does not log at all
 * but complies with the LoggerInterface.
 *
 * @package WooCommerce\WooCommerce\Logging\Logger
 */
declare (strict_types=1);
namespace WooCommerce\WooCommerce\Logging\Logger;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerTrait;
/**
 * Class NullLogger
 */
class NullLogger implements LoggerInterface
{
    use LoggerTrait;
    /**
     * Logs a message. Since its a NullLogger, it does not log at all.
     *
     * @param mixed  $level The logging level.
     * @param string $message The message.
     * @param array  $context The context.
     */
    public function log($level, $message, array $context = array())
    {
    }
}
