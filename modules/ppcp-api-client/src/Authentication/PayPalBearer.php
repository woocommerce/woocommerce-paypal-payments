<?php

/**
 * The PayPal bearer.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Class PayPalBearer
 */
class PayPalBearer implements \WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer
{
    use RequestTrait;
    const CACHE_KEY = 'ppcp-bearer';
    /**
     * The rate-limiter scope key for the client-credentials token.
     */
    const RATE_LIMIT_SCOPE = 'bearer';
    /**
     * The settings.
     *
     * @var ?SettingsProvider
     */
    protected $settings;
    /**
     * The cache.
     *
     * @var Cache
     */
    private $cache;
    /**
     * The host.
     *
     * @var string
     */
    private $host;
    /**
     * The client key.
     *
     * @var string
     */
    private $key;
    /**
     * The client secret.
     *
     * @var string
     */
    private $secret;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    private \WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter $rate_limiter;
    public function __construct(Cache $cache, string $host, string $key, string $secret, LoggerInterface $logger, ?SettingsProvider $settings, \WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter $rate_limiter)
    {
        $this->cache = $cache;
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
        $this->logger = $logger;
        $this->settings = $settings;
        $this->rate_limiter = $rate_limiter;
    }
    /**
     * Returns a bearer token.
     *
     * @return Token
     * @throws RuntimeException When request fails.
     */
    public function bearer(): Token
    {
        $cached = (string) $this->cache->get(self::CACHE_KEY);
        if ('' !== $cached) {
            try {
                $bearer = Token::from_json($cached);
                if ($bearer->is_valid()) {
                    return $bearer;
                }
            } catch (RuntimeException $error) {
                // Cached token is corrupt/unparsable; discard it and fetch a fresh one.
                $this->logger->debug('Discarding unparsable cached PayPal bearer token: ' . $error->getMessage());
            }
        }
        return $this->newBearer();
    }
    /**
     * Retrieves the client key for authentication.
     *
     * @return string The client ID from settings, or the key defined via constructor.
     */
    private function get_key(): string
    {
        if (is_null($this->settings)) {
            return $this->key;
        }
        $merchant_data = $this->settings->merchant_data();
        return $merchant_data->client_id;
    }
    /**
     * Retrieves the client secret for authentication.
     *
     * @return string The client secret from settings, or the value defined via constructor.
     */
    private function get_secret(): string
    {
        if (is_null($this->settings)) {
            return $this->secret;
        }
        $merchant_data = $this->settings->merchant_data();
        return $merchant_data->client_secret;
    }
    /**
     * Creates a new bearer token.
     *
     * @return Token
     * @throws RuntimeException When request fails.
     */
    private function newBearer(): Token
    {
        $key = $this->get_key();
        $secret = $this->get_secret();
        if ('' === $key || '' === $secret) {
            throw new RuntimeException('Cannot request a PayPal access token without a client ID and secret.');
        }
        $wait = $this->rate_limiter->retry_after_seconds(self::RATE_LIMIT_SCOPE);
        if (null !== $wait) {
            throw new RuntimeException(sprintf('PayPal token requests are paused for %d more seconds after a previous failure.', $wait));
        }
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials';
        $args = array('method' => 'POST', 'headers' => array(
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'Authorization' => 'Basic ' . base64_encode($key . ':' . $secret),
        ));
        $response = $this->request($url, $args);
        // A connection error (no response received) may be a momentary blip; retry
        // once so a single network hiccup doesn't arm the cool-down.
        if (is_wp_error($response)) {
            $response = $this->request($url, $args);
        }
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $status_code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
            $this->rate_limiter->register_failure(self::RATE_LIMIT_SCOPE, $status_code, $response);
            $error = new RuntimeException('Could not create token.');
            $log_args = $args;
            foreach (array_keys($log_args['headers']) as $header_name) {
                if (preg_match('/authorization|signature/i', $header_name)) {
                    $log_args['headers'][$header_name] = '[REDACTED]';
                }
            }
            $this->logger->warning($error->getMessage(), array('args' => $log_args, 'response' => $response));
            throw $error;
        }
        $token = Token::from_json($response['body']);
        $this->cache->set(self::CACHE_KEY, $token->as_json());
        $this->rate_limiter->clear(self::RATE_LIMIT_SCOPE);
        return $token;
    }
}
