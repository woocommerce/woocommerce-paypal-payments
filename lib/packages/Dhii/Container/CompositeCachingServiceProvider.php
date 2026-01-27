<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;
/**
 * A service provider that aggregates service definitions from other providers.
 */
class CompositeCachingServiceProvider implements ServiceProviderInterface
{
    /**
     * @var iterable<ServiceProviderInterface>
     */
    protected $providers;
    /**
     * @var ?iterable<callable>
     */
    protected $factories;
    /**
     * @var ?iterable<callable>
     */
    protected $extensions;
    /**
     * @param iterable<ServiceProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
        $this->factories = null;
        $this->extensions = null;
    }
    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidNullableReturnType
     * It isn't actually going to return null ever, because $factories will be filled during indexing.
     */
    public function getFactories()
    {
        if (!is_array($this->factories)) {
            $this->indexProviderDefinitions($this->providers);
        }
        /**
         * @psalm-suppress NullableReturnStatement
         * Not going to be null because will be populated by indexing
         */
        return $this->factories;
    }
    /**
     * @inheritDoc
     *
     * @psalm-suppress InvalidNullableReturnType
     * It isn't actually going to return null ever, because $factories will be filled during indexing.
     */
    public function getExtensions()
    {
        if (!is_array($this->extensions)) {
            $this->indexProviderDefinitions($this->providers);
        }
        /**
         * @psalm-suppress NullableReturnStatement
         * Not going to be null because will be populated by indexing
         */
        return $this->extensions;
    }
    /**
     * Indexes definitions in the specified service providers.
     *
     * Caches them internally.
     *
     * @param iterable<ServiceProviderInterface> $providers The providers to index.
     */
    protected function indexProviderDefinitions(iterable $providers): void
    {
        $factories = [];
        $extensions = [];
        foreach ($providers as $provider) {
            $factories = $this->mergeFactories($factories, $provider->getFactories());
            $extensions = $this->mergeExtensions($extensions, $provider->getExtensions());
        }
        $this->factories = $factories;
        $this->extensions = $extensions;
    }
    /**
     * Merges two maps of factories.
     *
     * @param callable[] $defaults The factory map to merge into.
     * @param callable[] $definitions The factory map to merge. Values from here will override defaults.
     *
     * @return callable[] The merged factories.
     */
    protected function mergeFactories(array $defaults, array $definitions): array
    {
        return array_merge($defaults, $definitions);
    }
    /**
     * Merged service extensions.
     *
     * @param callable[] $defaults
     * @param iterable<callable> $extensions
     *
     * @return callable[] The merged extensions.
     */
    protected function mergeExtensions(array $defaults, iterable $extensions): array
    {
        $merged = [];
        foreach ($extensions as $key => $extension) {
            if (isset($defaults[$key])) {
                $default = $defaults[$key];
                /**
                 * @psalm-suppress MissingClosureReturnType
                 * @psalm-suppress MissingClosureParamType
                 * Unable to specify mixed before PHP 8.
                 */
                $merged[$key] = function (PsrContainerInterface $c, $previous = null) use ($default, $extension) {
                    $result = $default($c, $previous);
                    $result = $extension($c, $result);
                    return $result;
                };
                unset($defaults[$key]);
            } else {
                $merged[$key] = $extension;
            }
        }
        $merged = $this->mergeFactories($defaults, $merged);
        return $merged;
    }
}
