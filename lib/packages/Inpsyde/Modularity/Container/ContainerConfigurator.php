<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Container;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * @phpstan-import-type Service from \WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule
 * @phpstan-import-type ExtendingService from \WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule
 */
class ContainerConfigurator
{
    /** @var array<string, Service> */
    private array $services = [];
    /** @var array<string, bool> */
    private array $factoryIds = [];
    private \WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Container\ServiceExtensions $extensions;
    private ?ContainerInterface $compiledContainer = null;
    /** @var ContainerInterface[] */
    private array $containers = [];
    /**
     * @param ContainerInterface[] $containers
     */
    public function __construct(array $containers = [], ?\WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Container\ServiceExtensions $extensions = null)
    {
        array_map([$this, 'addContainer'], $containers);
        $this->extensions = $extensions ?? new \WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Container\ServiceExtensions();
    }
    /**
     * @param ContainerInterface $container
     * @return void
     */
    public function addContainer(ContainerInterface $container): void
    {
        $this->containers[] = $container;
    }
    /**
     * @param string $id
     * @param Service $factory
     */
    public function addFactory(string $id, callable $factory): void
    {
        $this->addService($id, $factory);
        // We're using a hash table to detect later
        // via isset() if a Service as a Factory.
        $this->factoryIds[$id] = \true;
    }
    /**
     * @param string $id
     * @param Service $service
     * @return void
     */
    public function addService(string $id, callable $service): void
    {
        /*
         * We are being intentionally permissive here,
         * allowing a simple workflow for *intentional* overrides
         * while accepting the (small?) risk of *accidental* overrides
         * that could be hard to notice and debug.
         *
         * Clear a factory flag in case it was a factory.
         * If needs be, it will get re-added after this function completes.
         */
        unset($this->factoryIds[$id]);
        $this->services[$id] = $service;
    }
    /**
     * @param string $id
     * @return bool
     */
    public function hasService(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return \true;
        }
        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * @param string $id
     * @param ExtendingService $extender
     * @return void
     */
    public function addExtension(string $id, callable $extender): void
    {
        $this->extensions->add($id, $extender);
    }
    /**
     * @param string $id
     * @return bool
     */
    public function hasExtension(string $id): bool
    {
        return $this->extensions->has($id);
    }
    /**
     * @return ContainerInterface
     *
     * @phpstan-assert ContainerInterface $this->compiledContainer
     */
    public function createReadOnlyContainer(): ContainerInterface
    {
        if ($this->compiledContainer === null) {
            $this->compiledContainer = new \WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Container\ReadOnlyContainer($this->services, $this->factoryIds, $this->extensions, $this->containers);
        }
        return $this->compiledContainer;
    }
}
