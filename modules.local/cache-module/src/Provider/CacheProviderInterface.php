<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Provider;

use Psr\SimpleCache\CacheInterface;

interface CacheProviderInterface
{

    /**
     * Returns the cache interface for WordPress' transient system.
     *
     * @param string $key
     * @return CacheInterface
     */
    public function transientForKey(string $key) : CacheInterface;

    /**
     * Returns the cache interface for WordPress' object cache system.
     *
     * @param string $key
     * @return CacheInterface
     */
    public function cacheForKey(string $key) : CacheInterface;

    /**
     * Returns either a cache interface for transient or object cache based on the
     * running system.
     *
     * @param string $key
     * @return CacheInterface
     */
    public function cacheOrTransientForKey(string $key) : CacheInterface;
}
