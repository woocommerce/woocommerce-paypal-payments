<?php
/**
 * Generates user ID token for payer.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WP_Error;

/**
 * Class SdkClientToken
 */
class SdkClientToken {

	use RequestTrait;

	public const CACHE_KEY = 'sdk-client-token-data';

	/**
	 * The rate-limiter scope key for the SDK client token.
	 */
	public const RATE_LIMIT_SCOPE = 'sdk-client-token';

	private const ROTATE_AFTER_SECONDS = 60;

	private string $host;

	/**
	 * @phpstan-ignore property.onlyWritten (Read by RequestTrait.)
	 */
	private LoggerInterface $logger;

	private ClientCredentials $client_credentials;

	private Cache $cache;

	private TokenRateLimiter $rate_limiter;

	public function __construct(
		string $host,
		LoggerInterface $logger,
		ClientCredentials $client_credentials,
		Cache $cache,
		TokenRateLimiter $rate_limiter
	) {
		$this->host               = $host;
		$this->logger             = $logger;
		$this->client_credentials = $client_credentials;
		$this->cache              = $cache;
		$this->rate_limiter       = $rate_limiter;
	}

	/**
	 * Returns the client token for SDK `data-sdk-client-token`.
	 *
	 * @return string
	 *
	 * @throws PayPalApiException If the request fails.
	 * @throws RuntimeException If something unexpected happens.
	 */
	public function sdk_client_token(): string {
		return $this->sdk_client_token_data()['token'];
	}

	/**
	 * Returns the client token and the seconds it stays valid.
	 *
	 * When the "-fresh" marker is gone, one request gets a new token while the
	 * others keep the current one.
	 *
	 * @return array{token: string, expires_in: int}
	 *
	 * @throws PayPalApiException If the request fails.
	 * @throws RuntimeException If something unexpected happens.
	 */
	public function sdk_client_token_data(): array {
		$domain    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$domain    = (string) preg_replace( '/^www\./', '', $domain );
		$cache_key = self::CACHE_KEY . '-' . $domain;

		$cached = $this->cache->get( $cache_key );
		if ( ! is_array( $cached ) || ! isset( $cached['token'], $cached['expires_at'] ) ) {
			return $this->token_data( $this->request_token( $domain, $cache_key ) );
		}

		if ( false !== $this->cache->get( $cache_key . '-fresh' ) ) {
			return $this->token_data( $cached );
		}

		$this->cache->set( $cache_key . '-fresh', 1, self::ROTATE_AFTER_SECONDS );

		try {
			return $this->token_data( $this->request_token( $domain, $cache_key ) );
		} catch ( PayPalApiException | RuntimeException $exception ) {
			return $this->token_data( $cached );
		}
	}

	/**
	 * @param array{token: mixed, expires_at: mixed} $token
	 * @return array{token: string, expires_in: int}
	 */
	private function token_data( array $token ): array {
		return array(
			'token'      => (string) $token['token'],
			'expires_in' => max( 0, (int) $token['expires_at'] - time() ),
		);
	}

	/**
	 * @return array{token: string, expires_at: int}
	 *
	 * @throws PayPalApiException If the request fails.
	 * @throws RuntimeException If something unexpected happens.
	 */
	private function request_token( string $domain, string $cache_key ): array {
		if ( $this->client_credentials->is_empty() ) {
			throw new RuntimeException( 'Cannot request a PayPal client token without a client ID and secret.' );
		}

		$wait = $this->rate_limiter->retry_after_seconds( self::RATE_LIMIT_SCOPE );
		if ( null !== $wait ) {
			throw new RuntimeException( sprintf( 'PayPal token requests are paused for %d more seconds after a previous failure.', $wait ) );
		}

		$url = sprintf(
			'%s?grant_type=client_credentials&response_type=client_token&intent=sdk_init&domains[]=%s',
			trailingslashit( $this->host ) . 'v1/oauth2/token',
			rawurlencode( $domain )
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => $this->client_credentials->credentials(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
		);

		$response = $this->request( $url, $args );

		// Retry once on a connection error (a momentary blip) before giving up, so a
		// single network hiccup doesn't arm the cool-down.
		if ( $response instanceof WP_Error ) {
			$response = $this->request( $url, $args );
		}

		if ( $response instanceof WP_Error ) {
			$this->rate_limiter->register_failure( self::RATE_LIMIT_SCOPE, 0, $response );
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'], false );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$this->rate_limiter->register_failure( self::RATE_LIMIT_SCOPE, $status_code, $response );
			throw new PayPalApiException( $json, $status_code );
		}

		$expires_in = (int) $json->expires_in;
		$token      = array(
			'token'      => (string) $json->access_token,
			'expires_at' => time() + $expires_in,
		);

		// Stop serving the token shortly before it expires, so the SDK can still use it.
		if ( $expires_in > 150 ) {
			$expires_in -= 30;
		}

		$this->cache->set( $cache_key, $token, $expires_in );
		$this->cache->set( $cache_key . '-fresh', 1, self::ROTATE_AFTER_SECONDS );
		$this->rate_limiter->clear( self::RATE_LIMIT_SCOPE );

		return $token;
	}
}
