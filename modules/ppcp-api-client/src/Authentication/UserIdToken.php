<?php

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WP_Error;
/**
 * Generates user ID token for payer.
 */
class UserIdToken
{
    use RequestTrait;
    const CACHE_KEY = 'id-token-key';
    /**
     * The rate-limiter scope key for the user ID token.
     */
    const RATE_LIMIT_SCOPE = 'id-token';
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
     * Returns `id_token` which uniquely identifies the payer.
     *
     * @param string $target_customer_id Vaulted customer id.
     *
     * @return string
     *
     * @throws PayPalApiException If the request fails.
     * @throws RuntimeException If something unexpected happens.
     */
    public function id_token(string $target_customer_id = ''): string
    {
        $session_customer_id = '';
        if (!is_null(WC()->session) && method_exists(WC()->session, 'get_customer_id')) {
            $session_customer_id = WC()->session->get_customer_id();
        }
        if ($session_customer_id && $this->cache->has(self::CACHE_KEY . (string) $session_customer_id)) {
            return $this->cache->get(self::CACHE_KEY . (string) $session_customer_id);
        }
        if ($this->client_credentials->is_empty()) {
            throw new RuntimeException('Cannot request a PayPal user ID token without a client ID and secret.');
        }
        $wait = $this->rate_limiter->retry_after_seconds(self::RATE_LIMIT_SCOPE);
        if (null !== $wait) {
            throw new RuntimeException(sprintf('PayPal token requests are paused for %d more seconds after a previous failure.', $wait));
        }
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials&response_type=id_token';
        if ($target_customer_id) {
            $url = add_query_arg(array('target_customer_id' => $target_customer_id), $url);
        }
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
        $id_token = $json->id_token;
        if ($session_customer_id) {
            $this->cache->set(self::CACHE_KEY . (string) $session_customer_id, $id_token, 5);
        }
        $this->rate_limiter->clear(self::RATE_LIMIT_SCOPE);
        return $id_token;
    }
}
