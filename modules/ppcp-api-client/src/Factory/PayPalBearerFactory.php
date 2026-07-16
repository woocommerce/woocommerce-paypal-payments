<?php
/**
 * Builds the API bearer for a set of credentials, honoring the connection state.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\InMemoryCache;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

/**
 * Produces the bearer appropriate for the connection state, authenticated with the
 * passed client credentials.
 *
 * While no merchant is connected the store can only use a login-scoped
 * ConnectBearer; once connected it uses a full API PayPalBearer. This factory owns
 * that decision by consulting the shared ConnectionState, so callers do not have to.
 *
 * By default the PayPalBearer gets an isolated in-memory token cache and static
 * credentials, scoping its token to the passed credentials so it never mixes with
 * another account's cached token. Pass a persistent cache and a settings provider
 * to reproduce the shared, connection-bound bearer instead.
 */
class PayPalBearerFactory {

	private ConnectionState $connection_state;

	private TokenRateLimiter $rate_limiter;

	private LoggerInterface $logger;

	public function __construct(
		ConnectionState $connection_state,
		TokenRateLimiter $rate_limiter,
		LoggerInterface $logger
	) {
		$this->connection_state = $connection_state;
		$this->rate_limiter     = $rate_limiter;
		$this->logger           = $logger;
	}

	/**
	 * Builds the bearer for the given credentials.
	 *
	 * Returns a login-only ConnectBearer while the store is not connected, otherwise
	 * a full API PayPalBearer. The connection state is read from the shared
	 * ConnectionState unless $is_connected overrides it. Callers that operate outside
	 * the ambient connection (such as verifying credentials mid-connect) pass the
	 * override to force the API bearer.
	 *
	 * @param string            $host          The PayPal API host.
	 * @param string            $client_id     The client ID.
	 * @param string            $client_secret The client secret.
	 * @param ?bool             $is_connected  Overrides the ambient connection state when not null.
	 * @param ?Cache            $cache         Token cache; defaults to an isolated in-memory cache.
	 * @param ?SettingsProvider $settings      Resolves credentials dynamically when provided.
	 */
	public function create(
		string $host,
		string $client_id,
		string $client_secret,
		?bool $is_connected = null,
		?Cache $cache = null,
		?SettingsProvider $settings = null
	): Bearer {
		$is_connected = $is_connected ?? $this->connection_state->is_connected();

		if ( ! $is_connected ) {
			return new ConnectBearer();
		}

		return new PayPalBearer(
			$cache ?? new InMemoryCache(),
			$host,
			$client_id,
			$client_secret,
			$this->logger,
			$settings,
			$this->rate_limiter
		);
	}
}
