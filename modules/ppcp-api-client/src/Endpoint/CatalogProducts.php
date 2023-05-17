<?php
/**
 * The Catalog Products endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Product;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ProductFactory;

/**
 * Class CatalogProduct
 */
class CatalogProducts {
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
	 * Product factory.
	 *
	 * @var ProductFactory
	 */
	private $product_factory;

	/**
	 * CatalogProducts constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param ProductFactory  $product_factory Product factory.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		ProductFactory $product_factory,
		LoggerInterface $logger
	) {
		$this->host            = $host;
		$this->bearer          = $bearer;
		$this->product_factory = $product_factory;
		$this->logger          = $logger;
	}

	/**
	 * Creates a product.
	 *
	 * @param string $name Product name.
	 * @param string $description Product description.
	 *
	 * @return Product
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function create( string $name, string $description ): Product {
		$data = array(
			'name' => $name,
		);

		if ( $description ) {
			$data['description'] = $description;
		}

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/catalogs/products';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to create product.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $this->product_factory->from_paypal_response( $json );
	}

	/**
	 * Updates a Product.
	 *
	 * @param string $id Product ID.
	 * @param array  $data Data to update.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function update( string $id, array $data ): void {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/catalogs/products/' . $id;
		$args   = array(
			'method'  => 'PATCH',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to update product.' );
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
	 * Return a Product from the given ID.
	 *
	 * @param string $id Product ID.
	 *
	 * @return Product
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function product( string $id ): Product {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/catalogs/products/' . $id;
		$args   = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to get product.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $this->product_factory->from_paypal_response( $json );
	}
}
