<?php
/**
 * Manages an Onboarding Url / Token to preserve /v2/customer/partner-referrals action_url integrity.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use RuntimeException;

/**
 * Class OnboardingUrl
 */
class OnboardingUrl {

	/**
	 * The user ID to associate with the cache key
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * The cryptographically secure secret
	 *
	 * @var ?string
	 */
	private $secret = null;

	/**
	 * Unix Timestamp when token was generated
	 *
	 * @var ?int
	 */
	private $time = null;

	/**
	 * The "action_url" from /v2/customer/partner-referrals
	 *
	 * @var ?string
	 */
	private $url = null;

	/**
	 * The cache object
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * The prefix for the cache key
	 *
	 * @var string
	 */
	private $cache_key_prefix;

	/**
	 * The TTL for the cache.
	 *
	 * @var int
	 */
	private $cache_ttl = MONTH_IN_SECONDS;

	/**
	 * The TTL for the previous token cache.
	 *
	 * @var int
	 */
	private $previous_cache_ttl = 60;

	/**
	 * The constructor
	 *
	 * @param Cache  $cache The cache object to store the URL.
	 * @param string $cache_key_prefix The prefix for the cache entry.
	 * @param int    $user_id User ID to associate the link with.
	 */
	public function __construct(
		Cache $cache,
		string $cache_key_prefix,
		int $user_id
	) {
		$this->cache            = $cache;
		$this->cache_key_prefix = $cache_key_prefix;
		$this->user_id          = $user_id;
	}

	/**
	 * Instances the object with a $token.
	 *
	 * @param Cache  $cache The cache object where the URL is stored.
	 * @param string $token The token to validate.
	 * @param int    $user_id User ID to associate the link with.
	 * @return false|self
	 */
	public static function make_from_token( Cache $cache, string $token, int $user_id ) {
		if ( ! $token ) {
			return false;
		}

		$token_data = json_decode( UrlHelper::url_safe_base64_decode( $token ) ?: '', true );

		if ( ! $token_data ) {
			return false;
		}

		if ( ! isset( $token_data['u'] ) || ! isset( $token_data['k'] ) ) {
			return false;
		}

		if ( $token_data['u'] !== $user_id ) {
			return false;
		}

		return new self( $cache, $token_data['k'], $token_data['u'] );
	}

	/**
	 * Validates the token, if it's valid then delete it.
	 * If it's invalid don't delete it, to prevent malicious requests from invalidating the token.
	 *
	 * @param Cache  $cache The cache object where the URL is stored.
	 * @param string $token The token to validate.
	 * @param int    $user_id User ID to associate the link with.
	 * @return bool
	 */
	public static function validate_token_and_delete( Cache $cache, string $token, int $user_id ): bool {
		$onboarding_url = self::make_from_token( $cache, $token, $user_id );

		if ( $onboarding_url === false ) {
			return false;
		}

		if ( ! $onboarding_url->load() ) {
			return false;
		}

		if ( ( $onboarding_url->token() ?: '' ) !== $token ) {
			return false;
		}

		$onboarding_url->replace_previous_token( $token );
		$onboarding_url->delete();
		return true;
	}

	/**
	 * Validates the token against the previous token.
	 * Useful to don't throw errors on burst calls to endpoints.
	 *
	 * @param Cache  $cache The cache object where the URL is stored.
	 * @param string $token The token to validate.
	 * @param int    $user_id User ID to associate the link with.
	 * @return bool
	 */
	public static function validate_previous_token( Cache $cache, string $token, int $user_id ): bool {
		$onboarding_url = self::make_from_token( $cache, $token, $user_id );

		if ( $onboarding_url === false ) {
			return false;
		}

		return $onboarding_url->check_previous_token( $token );
	}

	/**
	 * Load cached data if is valid and initialize object.
	 *
	 * @return bool
	 */
	public function load(): bool {
		if ( ! $this->cache->has( $this->cache_key() ) ) {
			return false;
		}

		$cached_data = $this->cache->get( $this->cache_key() );

		if ( ! $this->validate_cache_data( $cached_data ) ) {
			return false;
		}

		$this->secret = $cached_data['secret'];
		$this->time   = $cached_data['time'];
		$this->url    = $cached_data['url'];

		return true;
	}

	/**
	 * Initializes the object
	 *
	 * @return void
	 */
	public function init(): void {
		try {
			$this->secret = bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			$this->secret = wp_generate_password( 16 );
		}

		$this->time = time();
		$this->url  = null;
	}

	/**
	 * Validates data from cache
	 *
	 * @param array $cache_data The data retrieved from the cache.
	 * @return bool
	 */
	private function validate_cache_data( $cache_data ): bool {
		if ( ! is_array( $cache_data ) ) {
			return false;
		}

		if (
			! ( $cache_data['user_id'] ?? false )
			|| ! ( $cache_data['hash_check'] ?? false )
			|| ! ( $cache_data['secret'] ?? false )
			|| ! ( $cache_data['time'] ?? false )
			|| ! ( $cache_data['url'] ?? false )
		) {
			return false;
		}

		if ( $cache_data['user_id'] !== $this->user_id ) {
			return false;
		}

		// Detect if salt has changed.
		if ( $cache_data['hash_check'] !== wp_hash( '' ) ) {
			return false;
		}

		// If we want we can also validate time for expiration eventually.

		return true;
	}

	/**
	 * Returns the URL
	 *
	 * @return string
	 * @throws RuntimeException Throws in case the URL isn't initialized.
	 */
	public function get(): string {
		if ( null === $this->url ) {
			throw new RuntimeException( 'Object not initialized.' );
		}
		return $this->url;
	}

	/**
	 * Returns the Token
	 *
	 * @return string
	 * @throws RuntimeException Throws in case the object isn't initialized.
	 */
	public function token(): string {
		if (
			null === $this->secret
			|| null === $this->time
			|| null === $this->user_id
		) {
			throw new RuntimeException( 'Object not initialized.' );
		}

		// Trim the hash to make sure the token isn't too long.
		$hash = substr(
			wp_hash(
				implode(
					'|',
					array(
						$this->cache_key_prefix,
						$this->user_id,
						$this->secret,
						$this->time,
					)
				)
			),
			0,
			32
		);

		$token = wp_json_encode(
			array(
				'k' => $this->cache_key_prefix,
				'u' => $this->user_id,
				'h' => $hash,
			)
		);

		if ( ! $token ) {
			throw new RuntimeException( 'Unable to generate token.' );
		}

		return UrlHelper::url_safe_base64_encode( $token );
	}

	/**
	 * Sets the URL
	 *
	 * @param string $url The URL to store in the cache.
	 * @return void
	 */
	public function set( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Persists the URL and related data in cache
	 *
	 * @return void
	 */
	public function persist(): void {
		if (
			null === $this->secret
			|| null === $this->time
			|| null === $this->user_id
			|| null === $this->url
		) {
			return;
		}

		$this->cache->set(
			$this->cache_key(),
			array(
				'hash_check' => wp_hash( '' ), // To detect if salt has changed.
				'secret'     => $this->secret,
				'time'       => $this->time,
				'user_id'    => $this->user_id,
				'url'        => $this->url,
			),
			$this->cache_ttl
		);
	}

	/**
	 * Deletes the token from cache
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->cache->delete( $this->cache_key() );
	}

	/**
	 * Returns the compiled cache key
	 *
	 * @return string
	 */
	private function cache_key(): string {
		return implode( '_', array( $this->cache_key_prefix, $this->user_id ) );
	}

	/**
	 * Returns the compiled cache key of the previous token
	 *
	 * @return string
	 */
	private function previous_cache_key(): string {
		return $this->cache_key() . '_previous';
	}

	/**
	 * Checks it the previous token matches the token provided.
	 *
	 * @param string $previous_token The previous token.
	 * @return bool
	 */
	private function check_previous_token( string $previous_token ): bool {
		if ( ! $this->cache->has( $this->previous_cache_key() ) ) {
			return false;
		}

		$cached_token = $this->cache->get( $this->previous_cache_key() );

		return $cached_token === $previous_token;
	}

	/**
	 * Replaces the previous token.
	 *
	 * @param string $previous_token The previous token.
	 * @return void
	 */
	private function replace_previous_token( string $previous_token ): void {
		$this->cache->set(
			$this->previous_cache_key(),
			$previous_token,
			$this->previous_cache_ttl
		);
	}

}
