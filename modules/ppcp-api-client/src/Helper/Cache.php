<?php

/**
 * Manages caching of values.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class Cache
 */
class Cache
{
    /**
     * The prefix for the value keys.
     *
     * @var string
     */
    private $prefix;
    /**
     * Cache constructor.
     *
     * @param string $prefix The prefix for the value keys.
     */
    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
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
        return get_transient($this->prefix . $key);
    }
    /**
     * Whether a value is stored or not.
     *
     * @param string $key The key for the value.
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        $value = $this->get($key);
        return \false !== $value;
    }
    /**
     * Deletes a cache.
     *
     * @param string $key The key.
     */
    public function delete(string $key): void
    {
        delete_transient($this->prefix . $key);
    }
    /**
     * Caches a value.
     *
     * @param string $key        The key under which the value should be cached.
     * @param mixed  $value      The value to cache.
     * @param int    $expiration Time until expiration in seconds.
     *
     * @return bool
     */
    public function set(string $key, $value, int $expiration = 0): bool
    {
        return (bool) set_transient($this->prefix . $key, $value, $expiration);
    }
    /**
     * Flushes all items of the current "cache group", i.e., items that use the defined prefix.
     *
     * @return void
     */
    public function flush(): void
    {
        global $wpdb;
        // Get a list of all transients with the relevant "group prefix" from the DB.
        $transients = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('_transient_' . $this->prefix) . '%'));
        /**
         * Delete each cache item individually to ensure WP can fire all relevant
         * actions, perform checks and other cleanup tasks and ensures eventually
         * object cache systems, like Redis, are kept in-sync with the DB.
         */
        foreach ($transients as $transient) {
            $key = str_replace('_transient_' . $this->prefix, '', $transient);
            $this->delete($key);
        }
    }
}
