<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerExceptionInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Throwable;

/**
 * Basic implementation of container exception.
 *
 * @package Dhii\Di
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
    /**
     * @var ContainerInterface|null
     */
    protected $container;

    /**
     * @param string         $message  The exception message.
     * @param int            $code     The exception code.
     * @param Throwable|null $previous The inner exception, if any.
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
