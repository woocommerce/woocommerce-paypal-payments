<?php
/**
 * Controls the endpoint for the incoming webhooks.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler;
use Psr\Log\LoggerInterface;

/**
 * Class IncomingWebhookEndpoint
 */
class IncomingWebhookEndpoint {

	const NAMESPACE = 'paypal/v1';
	const ROUTE     = 'incoming';

	/**
	 * The Webhook endpoint.
	 *
	 * @var WebhookEndpoint
	 */
	private $webhook_endpoint;

	/**
	 * The Webhook Factory.
	 *
	 * @var WebhookFactory
	 */
	private $webhook_factory;

	/**
	 * The Request handlers.
	 *
	 * @var RequestHandler[]
	 */
	private $handlers;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Whether requests need to be verified.
	 *
	 * @var bool
	 */
	private $verify_request;

	/**
	 * IncomingWebhookEndpoint constructor.
	 *
	 * @param WebhookEndpoint $webhook_endpoint The webhook endpoint.
	 * @param WebhookFactory  $webhook_factory The webhook factory.
	 * @param LoggerInterface $logger The logger.
	 * @param bool            $verify_request Whether requests need to be verified or not.
	 * @param RequestHandler  ...$handlers The handlers, which process a request in the end.
	 */
	public function __construct(
		WebhookEndpoint $webhook_endpoint,
		WebhookFactory $webhook_factory,
		LoggerInterface $logger,
		bool $verify_request,
		RequestHandler ...$handlers
	) {

		$this->webhook_endpoint = $webhook_endpoint;
		$this->webhook_factory  = $webhook_factory;
		$this->handlers         = $handlers;
		$this->logger           = $logger;
		$this->verify_request   = $verify_request;
	}

	/**
	 * Registers the endpoint.
	 *
	 * @return bool
	 */
	public function register(): bool {
		return (bool) register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => array(
					'POST',
				),
				'callback'            => array(
					$this,
					'handle_request',
				),
				'permission_callback' => array(
					$this,
					'verify_request',
				),
			)
		);
	}

	/**
	 * Verifies the current request.
	 *
	 * @return bool
	 */
	public function verify_request(): bool {
		if ( ! $this->verify_request ) {
			return true;
		}
		try {
			$data    = (array) get_option( WebhookRegistrar::KEY, array() );
			$webhook = $this->webhook_factory->from_array( $data );
			$result  = $this->webhook_endpoint->verify_current_request_for_webhook( $webhook );
			if ( ! $result ) {
				$this->logger->log(
					'error',
					__( 'Illegit Webhook request detected.', 'woocommerce-paypal-payments' )
				);
			}
			return $result;
		} catch ( RuntimeException $exception ) {
			$this->logger->log(
				'error',
				sprintf(
					// translators: %s is the error message.
					__(
						'Illegit Webhook request detected: %s',
						'woocommerce-paypal-payments'
					),
					$exception->getMessage()
				)
			);
			return false;
		}
	}

	/**
	 * Handles the request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {

		foreach ( $this->handlers as $handler ) {
			if ( $handler->responsible_for_request( $request ) ) {
				$response = $handler->handle_request( $request );
				$this->logger->log(
					'info',
					sprintf(
						// translators: %s is the event type.
						__( 'Webhook has been handled by %s', 'woocommerce-paypal-payments' ),
						( $handler->event_types() ) ? current( $handler->event_types() ) : ''
					),
					array(
						'request'  => $request,
						'response' => $response,
					)
				);
				return $response;
			}
		}

		$message = sprintf(
			// translators: %s is the request type.
			__( 'Could not find handler for request type %s', 'woocommerce-paypal-payments' ),
			$request['event_type']
		);
		$this->logger->log(
			'warning',
			$message,
			array(
				'request' => $request,
			)
		);
		$response = array(
			'success' => false,
			'message' => $message,
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Returns the URL to the endpoint.
	 *
	 * @return string
	 */
	public function url(): string {
		return rest_url( self::NAMESPACE . '/' . self::ROUTE );
	}

	/**
	 * Returns the event types, which are handled by the endpoint.
	 *
	 * @return array
	 */
	public function handled_event_types(): array {
		$event_types = array();
		foreach ( $this->handlers as $handler ) {
			$event_types = array_merge( $event_types, $handler->event_types() );
		}
		return array_unique( $event_types );
	}
}
