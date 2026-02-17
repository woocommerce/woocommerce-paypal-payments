<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\ContainerException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;
use UnexpectedValueException;
class DelegatingContainer implements ContainerInterface
{
    use StringTranslatingTrait;
    /**
     * @var ServiceProviderInterface
     */
    protected $provider;
    /**
     * @var PsrContainerInterface|null
     */
    protected $parent;
    /**
     */
    public function __construct(ServiceProviderInterface $provider, PsrContainerInterface $parent = null)
    {
        $this->provider = $provider;
        $this->parent = $parent;
    }
    /**
     * {@inheritDoc}
     */
    public function get($id)
    {
        $provider = $this->provider;
        $services = $provider->getFactories();
        if (!array_key_exists($id, $services)) {
            throw new NotFoundException($this->__('Service not found for key "%1$s"', [$id]), 0, null);
        }
        $service = $services[$id];
        try {
            $service = $this->invokeFactory($service);
        } catch (UnexpectedValueException $e) {
            throw new ContainerException($this->__('Could not create service "%1$s"', [$id]), 0, $e);
        }
        $extensions = $provider->getExtensions();
        if (!array_key_exists($id, $extensions)) {
            return $service;
        }
        $extension = $extensions[$id];
        try {
            $service = $this->invokeExtension($extension, $service);
        } catch (UnexpectedValueException $e) {
            throw new ContainerException($this->__('Could not extend service "%1$s"', [$id]), 0, $e);
        }
        return $service;
    }
    /**
     * {@inheritDoc}
     */
    public function has($id)
    {
        $services = $this->provider->getFactories();
        $id = (string) $id;
        return array_key_exists($id, $services);
    }
    /**
     * Retrieves a service by invoking its factory.
     *
     * @param callable $factory The factory to invoke.
     *
     * @return mixed The service created by the factory.
     *
     * @throws UnexpectedValueException If factory could not be invoked.
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    protected function invokeFactory(callable $factory)
    {
        if (!is_callable($factory)) {
            throw new UnexpectedValueException($this->__('Factory could not be invoked'), 0, null);
        }
        $baseContainer = $this->getBaseContainer();
        $service = $factory($baseContainer);
        return $service;
    }
    /**
     * Extends the service by invoking the extension with it.
     *
     * @param callable $extension The extension to invoke.
     * @param mixed $service The service to extend.
     *
     * @return mixed The extended service.
     *
     * @throws UnexpectedValueException If extension cannot be invoked.
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    protected function invokeExtension(callable $extension, $service)
    {
        if (!is_callable($extension)) {
            throw new UnexpectedValueException($this->__('Factory could not be invoked'), 0, null);
        }
        $baseContainer = $this->getBaseContainer();
        $service = $extension($baseContainer, $service);
        return $service;
    }
    /**
     * Retrieves the container to be used for definitions and extensions.
     *
     * @return PsrContainerInterface The parent container, if set. Otherwise, this instance.
     */
    protected function getBaseContainer(): PsrContainerInterface
    {
        return $this->parent instanceof PsrContainerInterface ? $this->parent : $this;
    }
}
