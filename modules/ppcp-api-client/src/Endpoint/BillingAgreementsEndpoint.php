<?php
/**
 * The billing agreements endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

/**
 * Class BillingAgreementsEndpoint
 */
class BillingAgreementsEndpoint {
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
	 * BillingAgreementsEndpoint constructor.
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
	 * Creates a billing agreement token.
	 *
	 * @param string $description The description.
	 * @param string $return_url The return URL.
	 * @param string $cancel_url The cancel URL.
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function create_token( string $description, string $return_url, string $cancel_url ): stdClass {
		$data = array(
			'description' => $description,
			'payer'       => array(
				'payment_method' => 'PAYPAL',
			),
			'plan'        => array(
				'type'                 => 'MERCHANT_INITIATED_BILLING',
				'merchant_preferences' => array(
					'return_url'            => $return_url,
					'cancel_url'            => $cancel_url,
					'skip_shipping_address' => true,
				),
			),
		);

		$bearer   = $this->bearer->bearer();
		$url      = trailingslashit( $this->host ) . 'v1/billing-agreements/agreement-tokens';
		$args     = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to create a billing agreement token.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $json;
	}

	/**
	 * Checks if reference transactions are enabled in account.
	 *
	 * @throws RuntimeException If the request fails (no auth, no connection, etc.).
	 */
	public function reference_transaction_enabled(): bool {
		try {
			$this->is_request_logging_enabled = false;

			try {
				$this->create_token(
					'Checking if reference transactions are enabled',
					'https://example.com/return',
					'https://example.com/cancel'
				);
			} finally {
				$this->is_request_logging_enabled = true;
			}

			return true;
		} catch ( PayPalApiException $exception ) {
			return false;
		}
	}
}
