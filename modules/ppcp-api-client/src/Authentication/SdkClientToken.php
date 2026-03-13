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
     * The host.
     *
     * @var string
     */
    private $host;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * The client credentials.
     *
     * @var ClientCredentials
     */
    private $client_credentials;
    /**
     * The cache.
     *
     * @var Cache
     */
    private $cache;
    /**
     * SdkClientToken constructor.
     *
     * @param string            $host The host.
     * @param LoggerInterface   $logger The logger.
     * @param ClientCredentials $client_credentials The client credentials.
     * @param Cache             $cache The cache.
     */
    public function __construct(string $host, LoggerInterface $logger, \WooCommerce\PayPalCommerce\ApiClient\Authentication\ClientCredentials $client_credentials, Cache $cache)
    {
        $this->host = $host;
        $this->logger = $logger;
        $this->client_credentials = $client_credentials;
        $this->cache = $cache;
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
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $domain = wp_unslash($_SERVER['HTTP_HOST'] ?? '');
        $domain = preg_replace('/^www\./', '', $domain);
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials&response_type=client_token&intent=sdk_init&domains[]=' . $domain;
        $args = array('method' => 'POST', 'headers' => array('Authorization' => $this->client_credentials->credentials(), 'Content-Type' => 'application/x-www-form-urlencoded'));
        $response = $this->request($url, $args);
        if ($response instanceof WP_Error) {
            throw new RuntimeException($response->get_error_message());
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            throw new PayPalApiException($json, $status_code);
        }
        $access_token = $json->access_token;
        $expires_in = (int) $json->expires_in;
        $this->cache->set(self::CACHE_KEY, $access_token, $expires_in);
        return $access_token;
    }
}
