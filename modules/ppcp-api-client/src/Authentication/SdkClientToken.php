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
	public function __construct(
		string $host,
		LoggerInterface $logger,
		ClientCredentials $client_credentials,
		Cache $cache
	) {
		$this->host               = $host;
		$this->logger             = $logger;
		$this->client_credentials = $client_credentials;
		$this->cache              = $cache;
	}

	/**
	 * Returns `sdk_client_token` which uniquely identifies the payer.
	 *
	 * @param string $target_customer_id Vaulted customer id.
	 *
	 * @return string
	 *
	 * @throws PayPalApiException If the request fails.
	 * @throws RuntimeException If something unexpected happens.
	 */
	public function sdk_client_token( string $target_customer_id = '' ): string {
		if ( $this->cache->has( self::CACHE_KEY ) ) {
			$user_id      = $this->cache->get( self::CACHE_KEY )['user_id'] ?? 0;
			$access_token = $this->cache->get( self::CACHE_KEY )['access_token'] ?? '';

			if ( $user_id === get_current_user_id() && $access_token ) {
				return $access_token;
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$domain = wp_unslash( $_SERVER['HTTP_HOST'] ?? '' );
		$domain = preg_replace( '/^www\./', '', $domain );

		$url = trailingslashit( $this->host ) . 'v1/oauth2/token?grant_type=client_credentials&response_type=client_token&intent=sdk_init&domains[]=' . $domain;

		if ( $target_customer_id ) {
			$url = add_query_arg(
				array(
					'target_customer_id' => $target_customer_id,
				),
				$url
			);
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => $this->client_credentials->credentials(),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException( $json, $status_code );
		}

		$access_token = $json->access_token;

		$data = array(
			'access_token' => $access_token,
			'user_id'      => get_current_user_id(),
		);

		$this->cache->set( self::CACHE_KEY, $data );

		return $access_token;
	}
}
