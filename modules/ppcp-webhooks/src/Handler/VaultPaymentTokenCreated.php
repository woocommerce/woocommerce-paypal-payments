<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.CREATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;

class VaultPaymentTokenCreated implements RequestHandler {

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var string
	 */
	protected $prefix;

	/**
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	public function __construct( LoggerInterface $logger, string $prefix, AuthorizedPaymentsProcessor $authorized_payments_processor ) {
		$this->logger                        = $logger;
		$this->prefix                        = $prefix;
		$this->authorized_payments_processor = $authorized_payments_processor;
	}

	public function event_types(): array {
		return array(
			'VAULT.PAYMENT-TOKEN.CREATED',
		);
	}

	public function responsible_for_request( \WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		$response = array( 'success' => false );

		$customer_id = $request['resource']['customer_id'] ?? '';
		if ( ! $customer_id ) {
			$message = 'No customer id was found.';
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new \WP_REST_Response( $response );
		}

		$wc_customer_id = (int) str_replace( $this->prefix, '', $customer_id );
		$this->authorized_payments_processor->capture_authorized_payments_for_customer( $wc_customer_id );

		$response['success'] = true;
		return rest_ensure_response( $response );
	}
}
