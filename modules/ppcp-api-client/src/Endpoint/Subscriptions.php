<?php
/**
 * The Subscriptions endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class Subscriptions
 */
class Subscriptions {

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
	 * Subscriptions constructor.
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
	 * Creates a subscription plan.
	 *
	 * @param string $product_id The product id.
	 *
	 * @return stdClass
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function create_plan(
		string $product_id,
		array $billing_cycles,
		array $payment_preferences
	): stdClass {

		$data = array(
			'product_id' => $product_id,
			'name' => 'Testing Plan',
			'billing_cycles' => array($billing_cycles),
			'payment_preferences' => $payment_preferences
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to create plan.' );
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
}
