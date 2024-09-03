<?php
/**
 * Orders API endpoints.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 * @link https://developer.paypal.com/docs/api/orders/v2/ Orders API documentation.
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WP_Error;

/**
 * Class Orders
 */
class Orders {

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
	 * Orders constructor.
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
	 * Creates a PayPal order.
	 *
	 * @param array $request_body The request body.
	 * @param array $headers The request headers.
	 * @return array
	 * @throws RuntimeException If something went wrong with the request.
	 * @throws PayPalApiException If something went wrong with the PayPal API request.
	 */
	public function create( array $request_body, array $headers = array() ): array {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders';

		$default_headers = array(
			'Authorization'     => 'Bearer ' . $bearer->token(),
			'Content-Type'      => 'application/json',
			'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
		);
		$headers         = array_merge(
			$default_headers,
			$headers
		);

		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $request_body ),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			$body = json_decode( $response['body'] );

			$message = $body->details[0]->description ?? '';
			if ( $message ) {
				throw new RuntimeException( $message );
			}

			throw new PayPalApiException(
				$body,
				$status_code
			);
		}

		return $response;
	}

	/**
	 * Confirms the given order.
	 *
	 * @link https://developer.paypal.com/docs/api/orders/v2/#orders_confirm
	 *
	 * @param array  $request_body The request body.
	 * @param string $id PayPal order ID.
	 * @return array
	 * @throws RuntimeException If something went wrong with the request.
	 * @throws PayPalApiException If something went wrong with the PayPal API request.
	 */
	public function confirm_payment_source( array $request_body, string $id ): array {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $id . '/confirm-payment-source';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
			),
			'body'    => wp_json_encode( $request_body ),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			$body = json_decode( $response['body'] );

			$message = $body->details[0]->description ?? '';
			if ( $message ) {
				throw new RuntimeException( $message );
			}

			throw new PayPalApiException(
				$body,
				$status_code
			);
		}

		return $response;
	}

	/**
	 * Get PayPal order by id.
	 *
	 * @param string $id PayPal order ID.
	 * @return array
	 * @throws RuntimeException If something went wrong with the request.
	 * @throws PayPalApiException If something went wrong with the PayPal API request.
	 */
	public function order( string $id ): array {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $id;

		$args = array(
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
			),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			$body = json_decode( $response['body'] );

			$message = $body->details[0]->description ?? '';
			if ( $message ) {
				throw new RuntimeException( $message );
			}

			throw new PayPalApiException(
				$body,
				$status_code
			);
		}

		return $response;
	}
}
