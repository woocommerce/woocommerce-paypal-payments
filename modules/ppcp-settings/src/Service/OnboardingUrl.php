<?php

/**
 * Manages an Onboarding Url / Token to preserve /v2/customer/partner-referrals action_url
 * integrity.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use Throwable;
class OnboardingUrl
{
    /**
     * The user ID to associate with the cache key
     */
    private int $user_id;
    /**
     * The cryptographically secure secret
     */
    private ?string $secret = null;
    /**
     * Unix Timestamp when token was generated
     */
    private ?int $time = null;
    /**
     * The "action_url" from /v2/customer/partner-referrals
     */
    private ?string $url = null;
    private Cache $cache;
    private string $cache_key_prefix;
    private int $cache_ttl = MONTH_IN_SECONDS;
    /**
     * The TTL for the previous token cache.
     */
    private int $previous_cache_ttl = 60;
    /**
     * The constructor
     *
     * @param Cache  $cache            The cache object to store the URL.
     * @param string $cache_key_prefix The prefix for the cache entry.
     * @param int    $user_id          User ID to associate the link with.
     */
    public function __construct(Cache $cache, string $cache_key_prefix, int $user_id)
    {
        $this->cache = $cache;
        $this->cache_key_prefix = $cache_key_prefix;
        $this->user_id = $user_id;
    }
    /**
     * Instances the object with a $token.
     *
     * @param Cache  $cache   The cache object where the URL is stored.
     * @param string $token   The token to validate.
     * @param int    $user_id User ID to associate the link with.
     * @return false|self
     */
    public static function make_from_token(Cache $cache, string $token, int $user_id)
    {
        if (!$token) {
            return \false;
        }
        try {
            $json_string = self::url_safe_base64_decode($token) ?: '';
            $token_data = json_decode($json_string, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            return \false;
        }
        if (!$token_data) {
            return \false;
        }
        if (!isset($token_data['u'], $token_data['k'])) {
            return \false;
        }
        if ($token_data['u'] !== $user_id) {
            return \false;
        }
        return new self($cache, $token_data['k'], $token_data['u']);
    }
    /**
     * Validates the token, if it's valid then delete it.
     * If it's invalid don't delete it, to prevent malicious requests from invalidating the token.
     *
     * @param Cache  $cache   The cache object where the URL is stored.
     * @param string $token   The token to validate.
     * @param int    $user_id User ID to associate the link with.
     * @return bool
     */
    public static function validate_token_and_delete(Cache $cache, string $token, int $user_id): bool
    {
        $onboarding_url = self::make_from_token($cache, $token, $user_id);
        if ($onboarding_url === \false) {
            return \false;
        }
        if (!$onboarding_url->load()) {
            return \false;
        }
        $expected_token = $onboarding_url->onboarding_token();
        if (!$expected_token || $expected_token !== $token) {
            return \false;
        }
        $onboarding_url->replace_previous_token($token);
        $onboarding_url->delete();
        return \true;
    }
    /**
     * Validates the token against the previous token.
     * Useful to don't throw errors on burst calls to endpoints.
     *
     * @param Cache  $cache   The cache object where the URL is stored.
     * @param string $token   The token to validate.
     * @param int    $user_id User ID to associate the link with.
     * @return bool
     */
    public static function validate_previous_token(Cache $cache, string $token, int $user_id): bool
    {
        $onboarding_url = self::make_from_token($cache, $token, $user_id);
        if ($onboarding_url === \false) {
            return \false;
        }
        return $onboarding_url->matches_previous_token($token);
    }
    /**
     * Load cached data if is valid and initialize object.
     *
     * @return bool
     */
    public function load(): bool
    {
        $key = $this->cache_key();
        if (!$this->cache->has($key)) {
            return \false;
        }
        $cached_data = $this->cache->get($key);
        if (!is_array($cached_data) || !$this->validate_cache_data($cached_data)) {
            return \false;
        }
        $this->secret = $cached_data['secret'];
        $this->time = $cached_data['time'];
        $this->url = $cached_data['url'];
        return \true;
    }
    public function init(): void
    {
        try {
            $this->secret = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $this->secret = wp_generate_password(16);
        }
        $this->time = time();
        $this->url = null;
    }
    private function validate_cache_data(array $cache_data): bool
    {
        if (!($cache_data['user_id'] ?? \false) || !($cache_data['hash_check'] ?? \false) || !($cache_data['secret'] ?? \false) || !($cache_data['time'] ?? \false) || !($cache_data['url'] ?? \false)) {
            return \false;
        }
        if ($cache_data['user_id'] !== $this->user_id) {
            return \false;
        }
        // Detect if salt has changed.
        if ($cache_data['hash_check'] !== wp_hash('')) {
            return \false;
        }
        return \true;
    }
    /**
     * Returns the Token
     *
     * @return string Empty string on failure, otherwise a base64 encoded payload.
     */
    public function onboarding_token(): string
    {
        if (null === $this->secret || null === $this->time) {
            return '';
        }
        // Trim the hash to make sure the token isn't too long.
        $hash = substr(wp_hash(implode('|', array($this->cache_key_prefix, $this->user_id, $this->secret, $this->time))), 0, 32);
        $token = wp_json_encode(array('k' => $this->cache_key_prefix, 'u' => $this->user_id, 'h' => $hash));
        if (!$token) {
            return '';
        }
        return self::url_safe_base64_encode($token);
    }
    public function get_onboarding_url(): string
    {
        return $this->url ?? '';
    }
    public function set_onboarding_url(string $url): void
    {
        $this->url = $url;
    }
    /**
     * Persists the URL and related data in cache
     *
     * @return void
     */
    public function persist(): void
    {
        if (null === $this->secret || null === $this->time || null === $this->url) {
            return;
        }
        $this->cache->set($this->cache_key(), array(
            'hash_check' => wp_hash(''),
            // To detect if salt has changed.
            'secret' => $this->secret,
            'time' => $this->time,
            'user_id' => $this->user_id,
            'url' => $this->url,
        ), $this->cache_ttl);
    }
    /**
     * Deletes the token from cache
     *
     * @return void
     */
    public function delete(): void
    {
        $this->cache->delete($this->cache_key());
    }
    private function cache_key(): string
    {
        return implode('_', array($this->cache_key_prefix, $this->user_id));
    }
    private function previous_cache_key(): string
    {
        return $this->cache_key() . '_previous';
    }
    private function matches_previous_token(string $previous_token): bool
    {
        if (!$this->cache->has($this->previous_cache_key())) {
            return \false;
        }
        $cached_token = $this->cache->get($this->previous_cache_key());
        return $cached_token === $previous_token;
    }
    private function replace_previous_token(string $previous_token): void
    {
        $this->cache->set($this->previous_cache_key(), $previous_token, $this->previous_cache_ttl);
    }
    private static function url_safe_base64_encode(string $string): string
    {
        //phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        $encoded_string = base64_encode($string);
        $url_safe_string = str_replace(array('+', '/'), array('-', '_'), $encoded_string);
        return rtrim($url_safe_string, '=');
    }
    /** @phpstan-ignore missingType.return */
    private static function url_safe_base64_decode(string $url_safe_string)
    {
        $padded_string = str_pad($url_safe_string, strlen($url_safe_string) % 4, '=', \STR_PAD_RIGHT);
        $encoded_string = str_replace(array('-', '_'), array('+', '/'), $padded_string);
        //phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        return base64_decode($encoded_string);
    }
}
