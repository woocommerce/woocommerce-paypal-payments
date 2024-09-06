<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module;

interface FactoryModule extends Module
{
    /**
     * Return application factories.
     *
     * Similar to `services`, but object created by given factories are not "cached", but a *new*
     * instance is returned everytime `get()` is called in the container.
     *
     * @return array<string, callable(\WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface $container):mixed>
     */
    public function factories(): array;
}
