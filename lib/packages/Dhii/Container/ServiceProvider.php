<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * A value object capable of providing services.
 *
 * @psalm-type Factory = callable(ContainerInterface): mixed
 * @psalm-type Extension = callable(ContainerInterface, mixed): mixed
 */
class ServiceProvider implements ServiceProviderInterface
{
    /** @var callable[] */
    protected array $factories;
    /**
     * @var callable[]
     */
    protected array $extensions;
    /**
     * @param callable[] $factories A map of service name to service factory.
     * @param callable[] $extensions A map of service name to service extension.
     */
    public function __construct(array $factories, array $extensions)
    {
        $this->factories = $factories;
        $this->extensions = $extensions;
    }
    /**
     * {@inheritDoc}
     */
    public function getFactories()
    {
        return $this->factories;
    }
    /**
     * {@inheritDoc}
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
