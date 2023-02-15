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
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentTokenActionLinks;
use WooCommerce\PayPalCommerce\ApiClient\Exception\AlreadyVaultedException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenActionLinksFactory;
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
	 * The PaymentTokenActionLinks factory.
	 *
	 * @var PaymentTokenActionLinksFactory
	 */
	private $payment_token_action_links_factory;

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
	 * @param string                         $host The host.
	 * @param Bearer                         $bearer The bearer.
	 * @param PaymentTokenFactory            $factory The payment token factory.
	 * @param PaymentTokenActionLinksFactory $payment_token_action_links_factory The PaymentTokenActionLinks factory.
	 * @param LoggerInterface                $logger The logger.
	 * @param CustomerRepository             $customer_repository The customer repository.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		PaymentTokenFactory $factory,
		PaymentTokenActionLinksFactory $payment_token_action_links_factory,
		LoggerInterface $logger,
		CustomerRepository $customer_repository
	) {

		$this->host                               = $host;
		$this->bearer                             = $bearer;
		$this->factory                            = $factory;
		$this->payment_token_action_links_factory = $payment_token_action_links_factory;
		$this->logger                             = $logger;
		$this->customer_repository                = $customer_repository;
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

	/**
	 * Starts the process of PayPal account vaulting (without payment), returns the links for further actions.
	 *
	 * @param int    $user_id The WP user id.
	 * @param string $return_url The URL to which the customer is redirected after finishing the approval.
	 * @param string $cancel_url The URL to which the customer is redirected if cancelled the operation.
	 *
	 * @return PaymentTokenActionLinks
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function start_paypal_token_creation(
		int $user_id,
		string $return_url,
		string $cancel_url
	): PaymentTokenActionLinks {
		$bearer = $this->bearer->bearer();

		$url = trailingslashit( $this->host ) . 'v2/vault/payment-tokens';

		$customer_id = $this->customer_repository->customer_id_for_user( ( $user_id ) );
		$data        = array(
			'customer_id'         => $customer_id,
			'source'              => array(
				'paypal' => array(
					'usage_type' => 'MERCHANT',
				),
			),
			'application_context' => array(
				'return_url' => $return_url,
				'cancel_url' => $cancel_url,
				// TODO: can use vault_on_approval to avoid /confirm-payment-token, but currently it's not working.
			),
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Failed to create payment token.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		$status = $json->status;
		if ( 'CUSTOMER_ACTION_REQUIRED' !== $status ) {
			throw new RuntimeException( 'Unexpected payment token creation status. ' . $status );
		}

		$links = $this->payment_token_action_links_factory->from_paypal_response( $json );

		return $links;
	}

	/**
	 * Finishes the process of PayPal account vaulting.
	 *
	 * @param string $approval_token The id of the approval token approved by the customer.
	 * @param int    $user_id The WP user id.
	 *
	 * @return string
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 * @throws AlreadyVaultedException When new token was not created (for example, already vaulted with this merchant).
	 */
	public function create_from_approval_token( string $approval_token, int $user_id ): string {
		$bearer = $this->bearer->bearer();

		$url = trailingslashit( $this->host ) . 'v2/vault/approval-tokens/' . $approval_token . '/confirm-payment-token';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Failed to create payment token from approval token.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $status_code ) {
			throw new AlreadyVaultedException( 'Already vaulted.' );
		}
		if ( 201 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $json->id;
	}
}
