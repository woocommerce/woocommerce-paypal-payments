<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;

//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
//phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter

class Transient extends AbstraktCache
{

    /**
     * The group variable acts basically as a prefix.
     *
     * @var string
     */
    private $group;

    public function __construct(string $group)
    {
        $this->group = $group;
    }

    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new InvalidCacheArgumentException('key argument must be a string.');
        }
        $value = get_transient($this->groupKey($key));
        if ($value) {
            $this->lastFound = true;
            return $value;
        }
        return $default;
    }

    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new InvalidCacheArgumentException('key argument must be a string.');
        }
        $ttl = $ttl ?: 0;
        $ttl = (is_a($ttl, \DateTime::class)) ? $ttl->format('%s') : $ttl;
        return (bool) set_transient($this->groupKey($key), $value, $ttl);
    }

    public function delete($key)
    {
        if (!is_string($key)) {
            throw new InvalidCacheArgumentException('key argument must be a string.');
        }

        return (bool) delete_transient($this->groupKey($key));
    }

    /**
     * The transient api has no way to safely delete all transients.
     * We can reliably delete all outdated transients. If this are the
     * only transients we delete, we return false.
     *
     * @return bool
     */
    public function clear()
    {
        if (wp_using_ext_object_cache()) {
            return (bool) wp_cache_flush();
        }
        wc_delete_expired_transients();
        return false;
    }

    private function groupKey(string $key) : string
    {
        return $this->group . $key;
    }
}
