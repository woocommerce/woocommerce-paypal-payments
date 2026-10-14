<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Exception;
/**
 * Provides PayPal's public JWK (JSON Web Key) for JWT signature verification.
 *
 * Implements transient caching to avoid fetching the JWKS on every request.
 *
 * @see PayPalJwkProviderTest
 */
class PayPalJwkProvider
{
    private const TRANSIENT_NAME = 'ppcp-ai-jwks';
    /**
     * The JWKS endpoint is lightweight, returning a small JSON object.
     * We keep the TTL low (one hour) for safety: In case the JSON changes, the
     * agentic endpoint is not blocked for too long.
     */
    private const TRANSIENT_TTL = HOUR_IN_SECONDS;
    private const JWKS_URL = 'https://www.paypal.ai/.well-known/jwks.json';
    /**
     * The JWKS entry may advertise an "alg" value, but we enforce the
     * expected algorithm here as a safety measure in case the jwks.json
     * is corrupted or compromised.
     */
    private const EXPECTED_ALGORITHM = 'RS256';
    private const EXPECTED_KEY_TYPE = 'RSA';
    /**
     * Returns all public keys from PayPal's JWKS, keyed by kid.
     *
     * Returning the full set (not just the first key) allows JWT::decode to match
     * tokens by kid during key rotation, when PayPal publishes old and new keys
     * simultaneously.
     *
     * @return array<string, Key> Parsed keys, or empty array on failure.
     */
    public function keys(): array
    {
        $jwks = $this->get_jwks_data();
        if (!$jwks) {
            return array();
        }
        try {
            return JWK::parseKeySet($jwks, self::EXPECTED_ALGORITHM);
        } catch (Exception $e) {
            return array();
        }
    }
    /**
     * Clean up the DB.
     */
    public static function flush(): void
    {
        delete_transient(self::TRANSIENT_NAME);
    }
    /**
     * Retrieves JWKS data from cache or fetches it from remote.
     *
     * @return array|null The JWKS data, or null on failure.
     */
    protected function get_jwks_data(): ?array
    {
        $jwks = $this->cache_get();
        if ($jwks) {
            return $jwks;
        }
        $jwks = $this->fetch_jwks_from_remote();
        if ($jwks) {
            $this->cache_set($jwks);
        }
        return $jwks;
    }
    /**
     * Retrieves JWKS data from transient cache.
     *
     * @return array|null The cached JWKS data, or null if not cached or invalid.
     */
    protected function cache_get(): ?array
    {
        $jwks = get_transient(self::TRANSIENT_NAME);
        if (!is_array($jwks) || empty($jwks['keys'])) {
            return null;
        }
        return $jwks;
    }
    /**
     * Stores JWKS data in transient cache.
     *
     * @param array $jwks The JWKS data to cache.
     */
    protected function cache_set(array $jwks): bool
    {
        return set_transient(self::TRANSIENT_NAME, $jwks, self::TRANSIENT_TTL);
    }
    /**
     * Fetches JWKS data from PayPal's well-known URL.
     *
     * @return array|null The JWKS data, or null on failure.
     */
    protected function fetch_jwks_from_remote(): ?array
    {
        $remove_user_agent = static function ($args, $url) {
            if (is_array($args) && $url === self::JWKS_URL) {
                $args['user-agent'] = '';
            }
            return $args;
        };
        add_filter('http_request_args', $remove_user_agent, 10, 2);
        $response = wp_remote_get(self::JWKS_URL);
        remove_filter('http_request_args', $remove_user_agent);
        if (is_wp_error($response)) {
            return null;
        }
        try {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, \true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return null;
            }
            $data['keys'] = $this->filter_jwks_keys($data['keys'] ?? array());
            if (empty($data['keys'])) {
                return null;
            }
            return $data;
        } catch (Exception $exception) {
            return null;
        }
    }
    /**
     * Strips any key that is not an RSA/RS256 entry before the data reaches the JWT library.
     *
     * @param array $keys Raw entries from the JWKS "keys" array.
     * @return array Filtered entries.
     */
    private function filter_jwks_keys(array $keys): array
    {
        $filtered_keys = array_filter($keys, static function ($key): bool {
            if (!is_array($key)) {
                return \false;
            }
            if (($key['kty'] ?? '') !== self::EXPECTED_KEY_TYPE) {
                return \false;
            }
            if (isset($key['alg']) && $key['alg'] !== self::EXPECTED_ALGORITHM) {
                return \false;
            }
            return \true;
        });
        return array_values($filtered_keys);
    }
}
