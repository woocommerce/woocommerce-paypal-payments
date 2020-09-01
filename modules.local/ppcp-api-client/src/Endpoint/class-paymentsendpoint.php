<?php
/**
 * The payments endpoint.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Psr\Log\LoggerInterface;

/**
 * Class PaymentsEndpoint
 */
class PaymentsEndpoint {

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
	 * The authorization factory.
	 *
	 * @var AuthorizationFactory
	 */
	private $authorizations_factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * PaymentsEndpoint constructor.
	 *
	 * @param string               $host The host.
	 * @param Bearer               $bearer The bearer.
	 * @param AuthorizationFactory $authorization_factory The authorization factory.
	 * @param LoggerInterface      $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		AuthorizationFactory $authorization_factory,
		LoggerInterface $logger
	) {

		$this->host                   = $host;
		$this->bearer                 = $bearer;
		$this->authorizations_factory = $authorization_factory;
		$this->logger                 = $logger;
	}

	/**
	 * Fetch an authorization by a given id.
	 *
	 * @param string $authorization_id The id.
	 *
	 * @return Authorization
	 * @throws RuntimeException If the request fails.
	 */
	public function authorization( string $authorization_id ): Authorization {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/payments/authorizations/' . $authorization_id;
		$args   = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);

		$response = $this->request( $url, $args );
		$json     = json_decode( $response['body'] );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not get authorized payment info.', 'woocommerce-paypal-commerce-gateway' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$authorization = $this->authorizations_factory->from_paypal_response( $json );
		return $authorization;
	}

	/**
	 * Capture an authorization by a given ID.
	 *
	 * @param string $authorization_id The id.
	 *
	 * @return Authorization
	 * @throws RuntimeException If the request fails.
	 */
	public function capture( string $authorization_id ): Authorization {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/payments/authorizations/' . $authorization_id . '/capture';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);

		$response = $this->request( $url, $args );
		$json     = json_decode( $response['body'] );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not capture authorized payment.', 'woocommerce-paypal-commerce-gateway' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$authorization = $this->authorizations_factory->from_paypal_response( $json );
		return $authorization;
	}
}
