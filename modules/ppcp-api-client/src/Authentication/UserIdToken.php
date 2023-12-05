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
use WP_Error;

/**
 * Class UserIdToken
 */
class UserIdToken {

	use RequestTrait;

	/**
	 * The host.
	 *
	 * @var string
	 */
	private $host;

	/**
	 * The bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * UserIdToken constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		LoggerInterface $logger
	) {
		$this->host   = $host;
		$this->bearer = $bearer;
		$this->logger = $logger;
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
		$bearer = $this->bearer->bearer();

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
				'Authorization' => 'Bearer ' . $bearer->token(),
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

		return $json->id_token;
	}
}
