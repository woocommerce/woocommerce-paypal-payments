<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\ContainerException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\NotFoundExceptionInterface;
use Traversable;
use UnexpectedValueException;
class CompositeContainer implements ContainerInterface
{
    use StringTranslatingTrait;
    /**
     * @var iterable<PsrContainerInterface>
     */
    protected $containers;
    /**
     * @param iterable<PsrContainerInterface> $containers The list of containers.
     */
    public function __construct(iterable $containers)
    {
        $this->containers = $containers;
    }
    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        /** @psalm-suppress RedundantCastGivenDocblockType
         * @psalm-suppress RedundantCast
         * Will remove when switching to PHP 7.2 and new PSR-11 interfaces
         */
        $key = (string) $key;
        foreach ($this->containers as $index => $container) {
            /**
             * @psalm-suppress InvalidCatch
             * The base interface does not extend Throwable, but in fact everything that is possible
             * in theory to catch will be Throwable, and PSR-11 exceptions will implement this interface
             */
            try {
                if ($container->has($key)) {
                    return $container->get($key);
                }
            } catch (NotFoundExceptionInterface $e) {
                throw new NotFoundException($this->__('Failed to retrieve value for key "%1$s" from container at index "%2$s"', [$key, $index]), 0, $e);
            } catch (Exception $e) {
                throw new ContainerException($this->__('Failed check for key "%1$s" on container at index "%2$s"', [$key, $index]), 0, $e);
            }
        }
        throw new NotFoundException($this->__('Key "%1$s" not found in any of the containers', [$key]), 0, null);
    }
    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        /** @psalm-suppress RedundantCastGivenDocblockType
         * Will remove when switching to PHP 7.2 and new PSR-11 interfaces
         */
        $key = (string) $key;
        foreach ($this->containers as $index => $container) {
            try {
                if ($container->has($key)) {
                    return \true;
                }
            } catch (Exception $e) {
                throw new ContainerException($this->__('Failed check for key "%1$s" on container at index "%2$s"', [$key, $index]), 0, $e);
            }
        }
        return \false;
    }
}
