<?php
/**
 * Fetches identity tokens.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class IdentityToken
 */
class IdentityToken {

	use RequestTrait;

	/**
	 * The Bearer.
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
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * The settings
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * IdentityToken constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param LoggerInterface $logger The logger.
	 * @param string          $prefix The prefix.
	 * @param Settings        $settings The settings.
	 */
	public function __construct( string $host, Bearer $bearer, LoggerInterface $logger, string $prefix, Settings $settings ) {
		$this->host     = $host;
		$this->bearer   = $bearer;
		$this->logger   = $logger;
		$this->prefix   = $prefix;
		$this->settings = $settings;
	}

	/**
	 * Generates a token for a specific customer.
	 *
	 * @param int $customer_id The id of the customer.
	 *
	 * @return Token
	 * @throws RuntimeException If the request fails.
	 */
	public function generate_for_customer( int $customer_id ): Token {

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/identity/generate-token';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);
		if (
			$customer_id
			&& ( $this->settings->has( 'vault_enabled' ) && $this->settings->get( 'vault_enabled' ) )
			&& defined( 'PPCP_FLAG_SUBSCRIPTION' ) && PPCP_FLAG_SUBSCRIPTION
		) {
			$args['body'] = wp_json_encode( array( 'customer_id' => $this->prefix . $customer_id ) );
		}

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__(
					'Could not create identity token.',
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

		$token = Token::from_json( $response['body'] );
		return $token;
	}
}
