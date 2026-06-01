<?php

/**
 * Generates user ID token for payer.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */
namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WP_Error;
/**
 * Class SdkClientToken
 */
class SdkClientToken
{
    use RequestTrait;
    const CACHE_KEY = 'sdk-client-token-key';
    /**
     * The rate-limiter scope key for the SDK client token.
     */
    const RATE_LIMIT_SCOPE = 'sdk-client-token';
    private string $host;
    private LoggerInterface $logger;
    private \WooCommerce\PayPalCommerce\ApiClient\Authentication\ClientCredentials $client_credentials;
    private Cache $cache;
    private \WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter $rate_limiter;
    public function __construct(string $host, LoggerInterface $logger, \WooCommerce\PayPalCommerce\ApiClient\Authentication\ClientCredentials $client_credentials, Cache $cache, \WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter $rate_limiter)
    {
        $this->host = $host;
        $this->logger = $logger;
        $this->client_credentials = $client_credentials;
        $this->cache = $cache;
        $this->rate_limiter = $rate_limiter;
    }
    /**
     * Returns the client token for SDK `data-sdk-client-token`.
     *
     * @return string
     *
     * @throws PayPalApiException If the request fails.
     * @throws RuntimeException If something unexpected happens.
     */
    public function sdk_client_token(): string
    {
        if ($this->cache->has(self::CACHE_KEY)) {
            return $this->cache->get(self::CACHE_KEY);
        }
        if ($this->client_credentials->is_empty()) {
            throw new RuntimeException('Cannot request a PayPal client token without a client ID and secret.');
        }
        $wait = $this->rate_limiter->retry_after_seconds(self::RATE_LIMIT_SCOPE);
        if (null !== $wait) {
            throw new RuntimeException(sprintf('PayPal token requests are paused for %d more seconds after a previous failure.', $wait));
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $domain = wp_unslash($_SERVER['HTTP_HOST'] ?? '');
        $domain = preg_replace('/^www\./', '', $domain);
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials&response_type=client_token&intent=sdk_init&domains[]=' . $domain;
        $args = array('method' => 'POST', 'headers' => array('Authorization' => $this->client_credentials->credentials(), 'Content-Type' => 'application/x-www-form-urlencoded'));
        $response = $this->request($url, $args);
        // Retry once on a connection error (a momentary blip) before giving up, so a
        // single network hiccup doesn't arm the cool-down.
        if ($response instanceof WP_Error) {
            $response = $this->request($url, $args);
        }
        if ($response instanceof WP_Error) {
            $this->rate_limiter->register_failure(self::RATE_LIMIT_SCOPE, 0, $response);
            throw new RuntimeException($response->get_error_message());
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            $this->rate_limiter->register_failure(self::RATE_LIMIT_SCOPE, $status_code, $response);
            throw new PayPalApiException($json, $status_code);
        }
        $access_token = $json->access_token;
        $expires_in = (int) $json->expires_in;
        $this->cache->set(self::CACHE_KEY, $access_token, $expires_in);
        $this->rate_limiter->clear(self::RATE_LIMIT_SCOPE);
        return $access_token;
    }
}
