<?php

/**
 * An in-memory version of Cache.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * An in-memory version of Cache. The data is kept only within the class instance.
 */
class InMemoryCache extends \WooCommerce\PayPalCommerce\ApiClient\Helper\Cache
{
    /**
     * The in-memory storage.
     *
     * @var array<string, mixed>
     */
    private array $data = array();
    /**
     * InMemoryCache constructor
     */
    public function __construct()
    {
        parent::__construct('');
    }
    /**
     * Gets a value.
     *
     * @param string $key The key under which the value is stored.
     *
     * @return mixed
     */
    public function get(string $key)
    {
        if (!array_key_exists($key, $this->data)) {
            return \false;
        }
        return $this->data[$key];
    }
    /**
     * Deletes a cache.
     *
     * @param string $key The key.
     */
    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }
    /**
     * Caches a value.
     *
     * @param string $key The key under which the value should be cached.
     * @param mixed  $value The value to cache.
     * @param int    $expiration Unused.
     *
     * @return bool
     */
    public function set(string $key, $value, int $expiration = 0): bool
    {
        $this->data[$key] = $value;
        return \true;
    }
}
