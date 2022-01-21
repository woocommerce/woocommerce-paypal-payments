<?php
/**
 * The payment token endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;

/**
 * Class PaymentTokenEndpoint
 */
class PaymentTokenEndpoint {

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
	 * The payment token factory.
	 *
	 * @var PaymentTokenFactory
	 */
	private $factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The customer repository.
	 *
	 * @var CustomerRepository
	 */
	protected $customer_repository;

	/**
	 * PaymentTokenEndpoint constructor.
	 *
	 * @param string              $host The host.
	 * @param Bearer              $bearer The bearer.
	 * @param PaymentTokenFactory $factory The payment token factory.
	 * @param LoggerInterface     $logger The logger.
	 * @param CustomerRepository  $customer_repository The customer repository.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		PaymentTokenFactory $factory,
		LoggerInterface $logger,
		CustomerRepository $customer_repository
	) {

		$this->host                = $host;
		$this->bearer              = $bearer;
		$this->factory             = $factory;
		$this->logger              = $logger;
		$this->customer_repository = $customer_repository;
	}

	/**
	 * Returns the payment tokens for a user.
	 *
	 * @param int $id The user id.
	 *
	 * @return PaymentToken[]
	 * @throws RuntimeException If the request fails.
	 */
	public function for_user( int $id ): array {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/vault/payment-tokens/?customer_id=' . $this->customer_repository->customer_id_for_user( $id );
		$args   = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not fetch payment token.', 'woocommerce-paypal-payments' )
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
		$json        = json_decode( $response['body'] );
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

		$tokens = array();
		foreach ( $json->payment_tokens as $token_value ) {
			$tokens[] = $this->factory->from_paypal_response( $token_value );
		}

		return $tokens;
	}

	/**
	 * Deletes a payment token.
	 *
	 * @param PaymentToken $token The token to delete.
	 *
	 * @return bool
	 * @throws RuntimeException If the request fails.
	 */
	public function delete_token( PaymentToken $token ): bool {

		$bearer = $this->bearer->bearer();

		$url  = trailingslashit( $this->host ) . 'v2/vault/payment-tokens/' . $token->id();
		$args = array(
			'method'  => 'DELETE',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not delete payment token.', 'woocommerce-paypal-payments' )
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

		return wp_remote_retrieve_response_code( $response ) === 204;
	}
}
