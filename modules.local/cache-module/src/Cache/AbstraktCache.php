<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Cache;

use Inpsyde\CacheModule\Exception\InvalidCacheArgumentException;

//phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
//phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoGetter
//phpcs:disable Inpsyde.CodeQuality.NoAccessors.NoSetter

abstract class AbstraktCache implements CacheInterface
{
    protected $lastFound = false;

    /**
     * @param iterable $keys
     * @param null $default
     * @return array|iterable
     * @throws InvalidCacheArgumentException
     */
    public function getMultiple($keys, $default = null)
    {
        if (!is_iterable($keys)) {
            throw new InvalidCacheArgumentException('keys argument must be a iterable.');
        }

        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->get($key, $default);
        }
        return $data;
    }

    /**
     * @param iterable $values
     * @param null $ttl
     * @return bool
     * @throws InvalidCacheArgumentException
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_iterable($values)) {
            throw new InvalidCacheArgumentException('values argument must be a iterable.');
        }

        $updatedItems = 0;
        foreach ($values as $key => $value) {
            $updatedItems += (int)$this->set($key, $value, $ttl);
        }
        return $updatedItems === count($values);
    }

    /**
     * @param iterable $keys
     * @return bool
     * @throws InvalidCacheArgumentException
     */
    public function deleteMultiple($keys)
    {
        if (!is_iterable($keys)) {
            throw new InvalidCacheArgumentException('keys argument must be a iterable.');
        }

        $deletedItems = 0;
        foreach ($keys as $key) {
            $deletedItems += (int)$this->delete($key);
        }
        return $deletedItems === count($keys);
    }

    /**
     * @param string $key
     * @return bool
     * @throws InvalidCacheArgumentException
     */
    public function has($key)
    {
        $this->get($key);
        return $this->lastFound === true;
    }
}
