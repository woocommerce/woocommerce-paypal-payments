<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Provider;

use Inpsyde\CacheModule\Cache\Cache;
use Inpsyde\CacheModule\Cache\Transient;
use Psr\SimpleCache\CacheInterface;

class CacheProvider implements CacheProviderInterface
{

    /**
     * @inheritDoc
     */
    public function transientForKey(string $key) : CacheInterface
    {
        return new Transient($key);
    }

    /**
     * @inheritDoc
     */
    public function cacheForKey(string $key) : CacheInterface
    {
        return new Cache($key);
    }

    /**
     * @inheritDoc
     */
    public function cacheOrTransientForKey(string $key) : CacheInterface
    {
        return (wp_using_ext_object_cache()) ? $this->cacheForKey($key) : $this->transientForKey($key);
    }
}
