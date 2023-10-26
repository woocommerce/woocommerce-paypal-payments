<?php
/**
 * The Payment Method Tokens endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

class PaymentMethodTokensEndpoint {

	use RequestTrait;

	private string $host;
	private Bearer $bearer;
	private LoggerInterface $logger;

	public function __construct(string $host, Bearer $bearer, LoggerInterface $logger)
	{
		$this->host = $host;
		$this->bearer = $bearer;
		$this->logger = $logger;
	}

	public function setup_tokens(PaymentSource $payment_source): stdClass {
		$data = array(
			'payment_source' => array(
				$payment_source->name() => $payment_source->properties()
			),
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v3/vault/setup-tokens';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'PayPal-Request-Id'         => uniqid( 'ppcp-', true ),
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to create setup token.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $json;
	}

	public function payment_tokens(PaymentSource $payment_source) {
		$data = array(
			'payment_source' => array(
				$payment_source->name() => $payment_source->properties()
			),
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v3/vault/payment-tokens';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'PayPal-Request-Id'         => uniqid( 'ppcp-', true ),
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to create setup token.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $json;
	}
}
