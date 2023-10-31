<?php
/**
 * The Billing Subscriptions endpoint.
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
 * Class BillingSubscriptions
 */
class BillingSubscriptions {

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
	 * BillingSubscriptions constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( string $host, Bearer $bearer, LoggerInterface $logger ) {
		$this->host   = $host;
		$this->bearer = $bearer;
		$this->logger = $logger;
	}

	/**
	 * Suspends a subscription.
	 *
	 * @param string $id Subscription ID.
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function suspend( string $id ):void {
		$data = array(
			'reason' => 'Suspended by customer',
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/subscriptions/' . $id . '/suspend';
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
			throw new RuntimeException( 'Not able to suspend subscription.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}
	}

	/**
	 * Activates a subscription.
	 *
	 * @param string $id Subscription ID.
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function activate( string $id ): void {
		$data = array(
			'reason' => 'Reactivated by customer',
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/subscriptions/' . $id . '/activate';
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
			throw new RuntimeException( 'Not able to reactivate subscription.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}
	}

	/**
	 * Cancels a Subscription.
	 *
	 * @param string $id Subscription ID.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function cancel( string $id ): void {
		$data = array(
			'reason' => 'Cancelled by customer',
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/subscriptions/' . $id . '/cancel';
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
			throw new RuntimeException( 'Not able to cancel subscription.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}
	}

	/**
	 * Returns a Subscription object from the given ID.
	 *
	 * @param string $id Subscription ID.
	 *
	 * @return stdClass
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function subscription( string $id ): stdClass {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/subscriptions/' . $id;
		$args   = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to get subscription.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $json;
	}


}
