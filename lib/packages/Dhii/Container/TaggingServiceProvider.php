<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
/**
 * A service provider that detects tags in factory docBlocks, and exposes them as services.
 *
 * A service may have a docBlock. The docBlock may contain various docBlock tags, such as `@param` or `@return`.
 * This class will detect `@tag {tagname}` tags in service docBlocks. `tagname` may be anything that a service
 * key may be - they exist in the same namespace. In fact, a `tagname` corresponds to a service
 * that returns a list of tagged services. To retrieve them, just resolve the tagname as a service.
 *
 * For each unique `tagname` in factory docBlocks, this service provider will create an extension with
 * an identical name. This extension at resolution time will resolve each tagged service by key,
 * and add resulting services to the list it is extending. To ensure there's always a list to extend,
 * this service provider will also add a service with an identical name, which resolves to an empty list.
 * All such "tag" services are empty list in the beginning of their resolution, so it doesn't matter
 * if it gets overwritten by another module's identical empty list.
 *
 * @psalm-import-type Factory from ServiceProvider
 * @psalm-import-type Extension from ServiceProvider
 */
class TaggingServiceProvider implements ServiceProviderInterface
{
    /** @var array<Factory> */
    protected array $factories;
    /** @var array<Extension> */
    protected array $extensions;
    public function __construct(ServiceProviderInterface $inner)
    {
        $this->factories = $inner->getFactories();
        $this->extensions = $inner->getExtensions();
        $this->indexTags();
    }
    /**
     * @inheritDoc
     */
    public function getFactories()
    {
        return $this->factories;
    }
    /**
     * @inheritDoc
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
    /**
     * Indexes tagged factories, and creates factories and extensions for tags.
     *
     * @throws ReflectionException If problem obtaining factory reflection.
     */
    protected function indexTags(): void
    {
        $tags = [];
        foreach ($this->factories as $serviceName => $factory) {
            if (is_string($factory)) {
                continue;
            }
            $reflection = is_object($factory) && get_class($factory) === 'Closure' ? new ReflectionFunction($factory) : new ReflectionObject($factory);
            $docBlock = $reflection->getDocComment();
            // No docblock
            if ($docBlock === \false) {
                continue;
            }
            $factoryTags = $this->getTagsFromDocBlock($docBlock);
            foreach ($factoryTags as $tag) {
                if (!isset($tags[$tag]) || !is_array($tags[$tag])) {
                    $tags[$tag] = [];
                }
                $tags[$tag][] = $serviceName;
            }
        }
        foreach ($tags as $tag => $taggedServiceNames) {
            $this->factories[$tag] = fn(): array => [];
            $this->extensions[$tag] = function (ContainerInterface $c, array $prev) use ($taggedServiceNames): array {
                return array_merge($prev, array_map(fn(string $serviceName) => $c->get($serviceName), $taggedServiceNames));
            };
        }
    }
    /**
     * Retrieves tags names that are part of a docBlock.
     *
     * @link https://www.php.net/manual/en/reflectionclass.getdoccomment.php#118606
     *
     * @param string $docBlock The docBlock.
     *
     * @return array<string> A list of tag names.
     */
    protected function getTagsFromDocBlock(string $docBlock): array
    {
        $regex = '#^\s*/?\**\s*(@tag\s*(?P<tags>[^\s]+))#m';
        preg_match_all($regex, $docBlock, $matches);
        return $matches['tags'];
    }
}
