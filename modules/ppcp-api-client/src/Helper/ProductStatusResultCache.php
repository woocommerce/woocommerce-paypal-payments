<?php

/**
 * Caches the API results for the ProductStatus class.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

class ProductStatusResultCache
{
    private const CACHE_KEY = 'woocommerce-ppcp-cache-product-status';
    private bool $loaded = \false;
    private array $cache = array();
    public function get(string $key): string
    {
        $this->load();
        if (!isset($this->cache[$key])) {
            return '';
        }
        $entry = $this->cache[$key];
        $now = $this->get_time();
        if (!empty($entry['expires_at']) && $entry['expires_at'] < $now) {
            $this->clear($key);
            return '';
        }
        return $entry['value'] ?? '';
    }
    public function set(string $key, string $value, int $expiration = 0): void
    {
        $this->load();
        $this->cache[$key] = array('value' => $value, 'expires_at' => $expiration > 0 ? $this->get_time() + $expiration : 0);
        $this->save();
    }
    public function clear(string $key): void
    {
        $this->load();
        unset($this->cache[$key]);
        $this->save();
    }
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->cache = array_map(static fn($value) => (array) $value, $this->load_from_storage());
        $this->loaded = \true;
    }
    private function save(): void
    {
        $this->save_to_storage($this->cache);
    }
    /**
     * Low-level data retrieval; can be overridden for testing.
     */
    protected function load_from_storage(): array
    {
        $data = get_transient(self::CACHE_KEY);
        return is_array($data) ? $data : array();
    }
    /**
     * Low-level data storage; can be overridden for testing.
     */
    protected function save_to_storage(array $data): void
    {
        set_transient(self::CACHE_KEY, $data);
    }
    /**
     * Low-level time access for expiration control; can be overridden for testing.
     */
    protected function get_time(): int
    {
        return time();
    }
}
