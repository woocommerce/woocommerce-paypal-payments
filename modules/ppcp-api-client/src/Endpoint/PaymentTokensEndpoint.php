<?php
/**
 * Payment tokens version 3 endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WP_Error;

/**
 * Class PaymentTokensEndpoint
 */
class PaymentTokensEndpoint {

	use RequestTrait;

	/**
	 * The bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

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
	 * PaymentTokensEndpoint constructor.
	 *
	 * @param string          $host The bearer.
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
	 * Deletes a payment token with the given id.
	 *
	 * @param string $id Payment token id.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When something went wrong with the request.
	 * @throws PayPalApiException When something went wrong deleting the payment token.
	 */
	public function delete( string $id ): void {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v3/vault/payment-tokens/' . $id;
		$args   = array(
			'method'  => 'DELETE',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			throw new PayPalApiException( $json, $status_code );
		}
	}
}
