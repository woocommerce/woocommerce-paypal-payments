<?php

/**
 * Failure registry.
 *
 * This class is used to remember API failures.
 * Mostly to prevent multiple failed API requests.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class FailureRegistry
 */
class FailureRegistry
{
    const CACHE_KEY = 'failure_registry';
    const CACHE_TIMEOUT = 60 * 60 * 24;
    // DAY_IN_SECONDS, if necessary we can increase this.
    const SELLER_STATUS_KEY = 'seller_status';
    /**
     * The Cache.
     *
     * @var Cache
     */
    private $cache;
    /**
     * FailureRegistry constructor.
     *
     * @param Cache $cache The Cache.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Helper\Cache $cache)
    {
        $this->cache = $cache;
    }
    /**
     * Returns if there was a failure within a given timeframe.
     *
     * @param string $key The cache key.
     * @param int    $seconds The timeframe in seconds.
     * @return bool
     */
    public function has_failure_in_timeframe(string $key, int $seconds): bool
    {
        $cache_key = $this->cache_key($key);
        $failure_time = $this->cache->get($cache_key);
        if (!$failure_time) {
            return \false;
        }
        $expiration = $failure_time + $seconds;
        return $expiration > time();
    }
    /**
     * Registers a failure.
     *
     * @param string $key The cache key.
     * @return void
     */
    public function add_failure(string $key)
    {
        $cache_key = $this->cache_key($key);
        $this->cache->set($cache_key, time(), self::CACHE_TIMEOUT);
    }
    /**
     * Clear a given failure.
     *
     * @param string $key The cache key.
     * @return void
     */
    public function clear_failures(string $key)
    {
        $cache_key = $this->cache_key($key);
        if ($this->cache->has($cache_key)) {
            $this->cache->delete($cache_key);
        }
    }
    /**
     * Build cache key.
     *
     * @param string $key The cache key.
     * @return string
     */
    private function cache_key(string $key): string
    {
        return implode('_', array(self::CACHE_KEY, $key));
    }
}
