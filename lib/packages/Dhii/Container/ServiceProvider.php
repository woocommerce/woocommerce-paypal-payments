<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;

/**
 * A value object capable of providing services.
 *
 * @package Dhii\Di
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @var callable[]
     */
    protected $factories;
    /**
     * @var callable[]
     */
    protected $extensions;

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
