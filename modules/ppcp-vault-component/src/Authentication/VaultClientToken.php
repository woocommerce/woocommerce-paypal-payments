<?php

/**
 * Generates a client token scoped to a buyer's PayPal vault id.
 *
 * @package WooCommerce\PayPalCommerce\VaultComponent\Authentication
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent\Authentication;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ClientCredentials;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WP_Error;
/**
 * Class VaultClientToken
 */
class VaultClientToken
{
    use RequestTrait;
    const CACHE_KEY_PREFIX = 'vault-client-token-key:';
    private string $host;
    private LoggerInterface $logger;
    private ClientCredentials $client_credentials;
    private Cache $cache;
    /**
     * VaultClientToken constructor.
     *
     * @param string            $host The host.
     * @param LoggerInterface   $logger The logger.
     * @param ClientCredentials $client_credentials The client credentials.
     * @param Cache             $cache The cache.
     */
    public function __construct(string $host, LoggerInterface $logger, ClientCredentials $client_credentials, Cache $cache)
    {
        $this->host = $host;
        $this->logger = $logger;
        $this->client_credentials = $client_credentials;
        $this->cache = $cache;
    }
    /**
     * Returns a `client_token` scoped to the given vault id, suitable for the PayPal SDK
     * `data-sdk-client-token` attribute.
     *
     * @param string $vault_id The PayPal vault id (buyer-scoped). Required.
     *
     * @return string
     *
     * @throws PayPalApiException If the request fails.
     * @throws RuntimeException If something unexpected happens.
     */
    public function client_token(string $vault_id): string
    {
        $cache_key = self::CACHE_KEY_PREFIX . $vault_id;
        if ($this->cache->has($cache_key)) {
            return $this->cache->get($cache_key);
        }
        $url = trailingslashit($this->host) . 'v1/oauth2/token';
        // Build the body manually so the literal `claims[]` key is preserved.
        $body = 'grant_type=client_credentials' . '&response_type=client_token' . '&claims%5B%5D=' . rawurlencode('vault_id:' . $vault_id);
        $args = array('method' => 'POST', 'headers' => array('Authorization' => $this->client_credentials->credentials(), 'Content-Type' => 'application/x-www-form-urlencoded'), 'body' => $body);
        $response = $this->request($url, $args);
        if ($response instanceof WP_Error) {
            $this->logger->error('Vault client_token request failed: ' . $response->get_error_message());
            throw new RuntimeException($response->get_error_message());
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        // A non-object body means the response could not be decoded (e.g. an HTML
        // error page or empty body). Guard against dereferencing null below.
        if (!$json instanceof \stdClass) {
            $this->logger->error('Vault client_token request returned an undecodable response body.');
            throw new RuntimeException('Could not parse the vault client_token response.');
        }
        if (200 !== $status_code) {
            throw new PayPalApiException($json, $status_code);
        }
        $access_token = (string) ($json->access_token ?? '');
        $expires_in = (int) ($json->expires_in ?? 0);
        if ($expires_in > 0) {
            $this->cache->set($cache_key, $access_token, $expires_in);
        }
        return $access_token;
    }
}
