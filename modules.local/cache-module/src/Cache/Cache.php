<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;

//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
//phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter

class Cache extends AbstraktCache
{

    private $group;
    public function __construct(string $group)
    {
        $this->group = $group;
    }

    /**
     * @param string $key
     * @param null $default
     * @return bool|mixed|null
     * @throws InvalidCacheArgumentException
     */
    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new InvalidCacheArgumentException('key argument must be a string.');
        }

        $lastFound = false;
        $data = wp_cache_get($key, $this->group, false, $lastFound);
        $this->lastFound = $lastFound;
        return ($this->lastFound) ? $data : $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $ttl
     * @return bool
     * @throws InvalidCacheArgumentException
     */
    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new InvalidCacheArgumentException('key argument must be a string.');
        }

        /** @var \DateInterval $ttl */
        $ttl = $ttl ?: 0;
        $ttl = (is_a($ttl, \DateTime::class)) ? $ttl->format('%s') : $ttl;

        return (bool)wp_cache_set($key, $value, $this->group, $ttl);
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidCacheArgumentException
     */
    public function delete($key)
    {
        if (!is_string($key)) {
            throw new InvalidCacheArgumentException('key argument must be a string.');
        }

        return (bool)wp_cache_delete($key, $this->group);
    }

    /**
     * @return bool
     */
    public function clear()
    {
        return (bool) wp_cache_flush();
    }
}
