<?php
/**
 * Orders API endpoints.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 * @link https://developer.paypal.com/docs/api/orders/v2/ Orders API documentation.
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WP_Error;

/**
 * Class Orders
 */
class Orders {

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
	 * Orders constructor.
	 *
	 * @param string $host
	 * @param Bearer $bearer
	 */
	public function __construct(
		string $host,
		Bearer $bearer
	) {
		$this->host = $host;
		$this->bearer = $bearer;
	}

	public function create(array $request_body, array $headers = array()): array {
		$bearer = $this->bearer->bearer();
		$url  = trailingslashit( $this->host ) . 'v2/checkout/orders';

		$default_headers = array(
			'Authorization' => 'Bearer ' . $bearer->token(),
			'Content-Type' => 'application/json',
		);
		$headers = array_merge(
			$default_headers,
			$headers
		);

		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $request_body ),
		);

		$response = wp_remote_get( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		return $response;
	}
}
