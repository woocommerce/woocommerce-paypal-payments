<?php
/**
 * The Partners Endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerStatusFactory;

/**
 * Class PartnersEndpoint
 */
class PartnersEndpoint {

	use RequestTrait;

	/**
	 * The Host URL.
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
	 * The seller status factory.
	 *
	 * @var SellerStatusFactory
	 */
	private $seller_status_factory;

	/**
	 * The partner ID.
	 *
	 * @var string
	 */
	private $partner_id;

	/**
	 * The merchant ID.
	 *
	 * @var string
	 */
	private $merchant_id;

	/**
	 * PartnersEndpoint constructor.
	 *
	 * @param string              $host The host.
	 * @param Bearer              $bearer The bearer.
	 * @param LoggerInterface     $logger The logger.
	 * @param SellerStatusFactory $seller_status_factory The seller status factory.
	 * @param string              $partner_id The partner ID.
	 * @param string              $merchant_id The merchant ID.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		LoggerInterface $logger,
		SellerStatusFactory $seller_status_factory,
		string $partner_id,
		string $merchant_id
	) {
		$this->host                  = $host;
		$this->bearer                = $bearer;
		$this->logger                = $logger;
		$this->seller_status_factory = $seller_status_factory;
		$this->partner_id            = $partner_id;
		$this->merchant_id           = $merchant_id;
	}

	/**
	 * Returns the current seller status.
	 *
	 * @return SellerStatus
	 * @throws RuntimeException When request could not be fullfilled.
	 */
	public function seller_status() : SellerStatus {
		$url      = trailingslashit( $this->host ) . 'v1/customer/partners/' . $this->partner_id . '/merchant-integrations/' . $this->merchant_id;
		$bearer   = $this->bearer->bearer();
		$args     = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);
		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {

			$error = new RuntimeException(
				__(
					'Could not fetch sellers status.',
					'woocommerce-paypal-payments'
				)
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

		$json        = json_decode( wp_remote_retrieve_body( $response ) );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$error = new PayPalApiException( $json, $status_code );
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

		$status = $this->seller_status_factory->from_paypal_reponse( $json );
		return $status;
	}
}
