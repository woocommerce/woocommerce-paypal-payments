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
    /**
     * PayPalBearer constructor.
     *
     * @param Cache             $cache The cache.
     * @param string            $host The host.
     * @param string            $key The key.
     * @param string            $secret The secret.
     * @param LoggerInterface   $logger The logger.
     * @param ?SettingsProvider $settings The settings.
     */
    public function __construct(Cache $cache, string $host, string $key, string $secret, LoggerInterface $logger, ?SettingsProvider $settings)
    {
        $this->cache = $cache;
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
        $this->logger = $logger;
        $this->settings = $settings;
    }
    /**
     * Returns a bearer token.
     *
     * @return Token
     * @throws RuntimeException When request fails.
     */
    public function bearer(): Token
    {
        try {
            $bearer = Token::from_json((string) $this->cache->get(self::CACHE_KEY));
            return $bearer->is_valid() ? $bearer : $this->newBearer();
        } catch (RuntimeException $error) {
            return $this->newBearer();
        }
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
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials';
        $args = array('method' => 'POST', 'headers' => array(
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'Authorization' => 'Basic ' . base64_encode($key . ':' . $secret),
        ));
        $response = $this->request($url, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $error = new RuntimeException(__('Could not create token.', 'woocommerce-paypal-payments'));
            $this->logger->warning($error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $token = Token::from_json($response['body']);
        $this->cache->set(self::CACHE_KEY, $token->as_json());
        return $token;
    }
}
