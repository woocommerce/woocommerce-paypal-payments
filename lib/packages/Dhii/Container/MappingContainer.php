<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * A container implementation that can map results from another container using a callback.
 *
 * **Example usage**:
 *
 * ```php
 * $container = new Container([
 *      'first' => 'Paul',
 *      'second' => 'JC',
 *      'third' => 'Alex',
 * ]);
 *
 * $mContainer = new MappingContainer($container, function ($name) {
 *      return $name . ' Denton';
 * });
 *
 * $mContainer->get('first');  // "Paul Denton"
 * $mContainer->get('second'); // "JC Denton"
 *
 * // We don't talk about Alex
 * ```
 *
 * @since [*next-version*]
 */
class MappingContainer implements ContainerInterface
{
    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * @since [*next-version*]
     *
     * @var callable
     */
    protected $callback;

    /**
     * @since [*next-version*]
     *
     * @var PsrContainerInterface
     */
    protected $inner;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param PsrContainerInterface $inner    The container instance to decorate.
     * @param callable              $callback The callback to invoke on get. It will be passed 3 parameters:
     *                                         * The inner container's value for the key being fetched.
     *                                         * The key being fetched.
     *                                         * A reference to this container instance.
     */
    public function __construct(PsrContainerInterface $inner, callable $callback)
    {
        $this->callback = $callback;
        $this->inner = $inner;
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    public function get($key)
    {
        return ($this->callback)($this->inner->get($key), $key, $this);
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    public function has($key)
    {
        return $this->inner->has($key);
    }
}
