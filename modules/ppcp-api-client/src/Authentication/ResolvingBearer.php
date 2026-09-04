<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ApiHostResolver;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
/**
 * Resolves the API bearer to use for the current connection state.
 *
 * A Bearer that mirrors ApiHostResolver's approach for the host: is_connected()
 * decides between ConnectBearer and PayPalBearer on every call rather than
 * freezing that choice at construction time. Without this, a Bearer resolved
 * (e.g. via rest_api_init building the webhook controller) before
 * ConnectionState::connect() runs in the same request stays a ConnectBearer -
 * a hardcoded placeholder token - for the rest of that request, even after
 * the merchant is connected and api.host has moved on to the real PayPal API.
 */
class ResolvingBearer implements \WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer
{
    private ConnectionState $connection_state;
    private Cache $cache;
    private ApiHostResolver $host_resolver;
    private string $key;
    private string $secret;
    private LoggerInterface $logger;
    private ?SettingsProvider $settings;
    private \WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter $rate_limiter;
    public function __construct(ConnectionState $connection_state, Cache $cache, ApiHostResolver $host_resolver, string $key, string $secret, LoggerInterface $logger, ?SettingsProvider $settings, \WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter $rate_limiter)
    {
        $this->connection_state = $connection_state;
        $this->cache = $cache;
        $this->host_resolver = $host_resolver;
        $this->key = $key;
        $this->secret = $secret;
        $this->logger = $logger;
        $this->settings = $settings;
        $this->rate_limiter = $rate_limiter;
    }
    /**
     * Returns the bearer to use right now.
     *
     * Must be called fresh for every request, not resolved once and cached -
     * see the class docblock.
     */
    public function bearer(): Token
    {
        if (!$this->connection_state->is_connected()) {
            return (new \WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer())->bearer();
        }
        return (new \WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer($this->cache, $this->host_resolver->host(), $this->key, $this->secret, $this->logger, $this->settings, $this->rate_limiter))->bearer();
    }
}
