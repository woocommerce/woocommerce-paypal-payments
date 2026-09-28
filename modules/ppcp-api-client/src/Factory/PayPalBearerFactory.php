<?php

/**
 * Builds the API bearer for a set of credentials.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\InMemoryCache;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Produces the bearer that matches the supplied credentials.
 *
 * Credential presence is the connection signal: with both a client ID and secret
 * the factory builds an authenticated PayPalBearer, otherwise it returns a
 * login-only ConnectBearer (nothing is connected, so there is nothing to
 * authenticate with). This mirrors PayPalBearer itself, which cannot fetch a token
 * without credentials.
 *
 * By default the PayPalBearer gets an isolated in-memory token cache and static
 * credentials, scoping its token to the passed credentials so it never mixes with
 * another account's cached token. Pass a persistent cache and a settings provider
 * to reproduce the shared, connection-bound bearer instead; when the settings
 * provider resolves credentials dynamically, still pass the current client ID and
 * secret so the bearer-type decision stays correct.
 */
class PayPalBearerFactory
{
    private TokenRateLimiter $rate_limiter;
    private LoggerInterface $logger;
    public function __construct(TokenRateLimiter $rate_limiter, LoggerInterface $logger)
    {
        $this->rate_limiter = $rate_limiter;
        $this->logger = $logger;
    }
    /**
     * Builds the bearer for the given credentials.
     *
     * Returns an authenticated PayPalBearer when both credentials are present,
     * otherwise a login-only ConnectBearer.
     *
     * @param string            $host          The PayPal API host.
     * @param string            $client_id     The client ID.
     * @param string            $client_secret The client secret.
     * @param ?Cache            $cache         Token cache; defaults to an isolated in-memory cache.
     * @param ?SettingsProvider $settings      Resolves credentials dynamically when provided.
     */
    public function create(string $host = '', string $client_id = '', string $client_secret = '', ?Cache $cache = null, ?SettingsProvider $settings = null): Bearer
    {
        if ('' === $client_id || '' === $client_secret) {
            return new ConnectBearer();
        }
        return new PayPalBearer($cache ?? new InMemoryCache(), $host, $client_id, $client_secret, $this->logger, $settings, $this->rate_limiter);
    }
}
