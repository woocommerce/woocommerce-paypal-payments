<?php
/**
 * The webhook endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use Psr\Log\LoggerInterface;

/**
 * Class WebhookEndpoint
 */
class WebhookEndpoint {

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
	 * The webhook factory.
	 *
	 * @var WebhookFactory
	 */
	private $webhook_factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * WebhookEndpoint constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param WebhookFactory  $webhook_factory The webhook factory.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		WebhookFactory $webhook_factory,
		LoggerInterface $logger
	) {

		$this->host            = $host;
		$this->bearer          = $bearer;
		$this->webhook_factory = $webhook_factory;
		$this->logger          = $logger;
	}

	/**
	 * Creates a webhook with PayPal.
	 *
	 * @param Webhook $hook The webhook to create.
	 *
	 * @return Webhook
	 * @throws RuntimeException If the request fails.
	 */
	public function create( Webhook $hook ): Webhook {
		/**
		 * An hook, which has an ID has already been created.
		 */
		if ( $hook->id() ) {
			return $hook;
		}
		$bearer   = $this->bearer->bearer();
		$url      = trailingslashit( $this->host ) . 'v1/notifications/webhooks';
		$args     = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $hook->to_array() ),
		);
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Not able to create a webhook.', 'woocommerce-paypal-payments' )
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

		$hook = $this->webhook_factory->from_paypal_response( $json );
		return $hook;
	}

	/**
	 * Deletes a webhook.
	 *
	 * @param Webhook $hook The webhook to delete.
	 *
	 * @return bool
	 * @throws RuntimeException If the request fails.
	 */
	public function delete( Webhook $hook ): bool {
		if ( ! $hook->id() ) {
			return false;
		}

		$bearer   = $this->bearer->bearer();
		$url      = trailingslashit( $this->host ) . 'v1/notifications/webhooks/' . $hook->id();
		$args     = array(
			'method'  => 'DELETE',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
			),
		);
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Not able to delete the webhook.', 'woocommerce-paypal-payments' )
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
	 * Verifies if a webhook event is legitimate.
	 *
	 * @param string    $auth_algo The auth algo.
	 * @param string    $cert_url The cert URL.
	 * @param string    $transmission_id The transmission id.
	 * @param string    $transmission_sig The transmission signature.
	 * @param string    $transmission_time The transmission time.
	 * @param string    $webhook_id The webhook id.
	 * @param \stdClass $webhook_event The webhook event.
	 *
	 * @return bool
	 * @throws RuntimeException If the request fails.
	 */
	public function verify_event(
		string $auth_algo,
		string $cert_url,
		string $transmission_id,
		string $transmission_sig,
		string $transmission_time,
		string $webhook_id,
		\stdClass $webhook_event
	): bool {

		$bearer   = $this->bearer->bearer();
		$url      = trailingslashit( $this->host ) . 'v1/notifications/verify-webhook-signature';
		$args     = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'transmission_id'   => $transmission_id,
					'transmission_time' => $transmission_time,
					'cert_url'          => $cert_url,
					'auth_algo'         => $auth_algo,
					'transmission_sig'  => $transmission_sig,
					'webhook_id'        => $webhook_id,
					'webhook_event'     => $webhook_event,
				)
			),
		);
		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Not able to verify webhook event.', 'woocommerce-paypal-payments' )
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
		$json = json_decode( $response['body'] );
		return isset( $json->verification_status ) && 'SUCCESS' === $json->verification_status;
	}

	/**
	 * Verifies if the current request is a legit webhook event.
	 *
	 * @param Webhook $webhook The webhook.
	 *
	 * @return bool
	 * @throws RuntimeException If the request fails.
	 */
	public function verify_current_request_for_webhook( Webhook $webhook ): bool {

		if ( ! $webhook->id() ) {
			$error = new RuntimeException(
				__( 'Not a valid webhook to verify.', 'woocommerce-paypal-payments' )
			);
			$this->logger->log( 'warning', $error->getMessage(), array( 'webhook' => $webhook ) );
			throw $error;
		}

		$expected_headers = array(
			'PAYPAL-AUTH-ALGO'         => '',
			'PAYPAL-CERT-URL'          => '',
			'PAYPAL-TRANSMISSION-ID'   => '',
			'PAYPAL-TRANSMISSION-SIG'  => '',
			'PAYPAL-TRANSMISSION-TIME' => '',
		);
		$headers          = getallheaders();
		foreach ( $headers as $key => $header ) {
			$key = strtoupper( $key );
			if ( isset( $expected_headers[ $key ] ) ) {
				$expected_headers[ $key ] = $header;
			}
		};

		foreach ( $expected_headers as $key => $value ) {
			if ( ! empty( $value ) ) {
				continue;
			}

			$error = new RuntimeException(
				sprintf(
					// translators: %s is the headers key.
					__(
						'Not a valid webhook event. Header %s is missing',
						'woocommerce-paypal-payments'
					),
					$key
				)
			);
			$this->logger->log( 'warning', $error->getMessage(), array( 'webhook' => $webhook ) );
			throw $error;
		}

		$request_body = json_decode( file_get_contents( 'php://input' ) );
		return $this->verify_event(
			$expected_headers['PAYPAL-AUTH-ALGO'],
			$expected_headers['PAYPAL-CERT-URL'],
			$expected_headers['PAYPAL-TRANSMISSION-ID'],
			$expected_headers['PAYPAL-TRANSMISSION-SIG'],
			$expected_headers['PAYPAL-TRANSMISSION-TIME'],
			$webhook->id(),
			$request_body ? $request_body : new \stdClass()
		);
	}
}
