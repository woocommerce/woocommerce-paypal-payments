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
 * Class UserIdToken
 */
class UserIdToken {

	use RequestTrait;

	const CACHE_KEY = 'id-token-key';

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
	 * UserIdToken constructor.
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
	 * Returns `id_token` which uniquely identifies the payer.
	 *
	 * @param string $target_customer_id Vaulted customer id.
	 *
	 * @return string
	 *
	 * @throws PayPalApiException If the request fails.
	 * @throws RuntimeException If something unexpected happens.
	 */
	public function id_token( string $target_customer_id = '' ): string {
		$session_customer_id = '';
		if ( ! is_null( WC()->session ) && method_exists( WC()->session, 'get_customer_id' ) ) {
			$session_customer_id = WC()->session->get_customer_id();
		}

		if ( $session_customer_id && $this->cache->has( self::CACHE_KEY . (string) $session_customer_id ) ) {
			return $this->cache->get( self::CACHE_KEY . (string) $session_customer_id );
		}

		$url = trailingslashit( $this->host ) . 'v1/oauth2/token?grant_type=client_credentials&response_type=id_token';
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

		$id_token = $json->id_token;

		if ( $session_customer_id ) {
			$this->cache->set( self::CACHE_KEY . (string) $session_customer_id, $id_token, 5 );
		}

		return $id_token;
	}
}
