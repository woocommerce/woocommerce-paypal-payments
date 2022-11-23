<?php
/**
 * Handles the Webhook VAULT.PAYMENT-TOKEN.CREATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class VaultPaymentTokenCreated
 */
class VaultPaymentTokenCreated implements RequestHandler {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * The authorized payment processor.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * WooCommerce Payment token PayPal.
	 *
	 * @var PaymentTokenPayPal
	 */
	private $payment_token_paypal;

	/**
	 * VaultPaymentTokenCreated constructor.
	 *
	 * @param LoggerInterface             $logger The logger.
	 * @param string                      $prefix The prefix.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The authorized payment processor.
	 * @param PaymentTokenPayPal $payment_token_paypal WooCommerce Payment token PayPal.
	 */
	public function __construct(
		LoggerInterface $logger,
		string $prefix,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		PaymentTokenPayPal $payment_token_paypal
	) {
		$this->logger                        = $logger;
		$this->prefix                        = $prefix;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->payment_token_paypal = $payment_token_paypal;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array(
			'VAULT.PAYMENT-TOKEN.CREATED',
		);
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );

		$customer_id = null !== $request['resource'] && isset( $request['resource']['customer_id'] )
			? $request['resource']['customer_id']
			: '';
		if ( ! $customer_id ) {
			$message = 'No customer id was found.';
			$this->logger->warning( $message, array( 'request' => $request ) );
			$response['message'] = $message;
			return new WP_REST_Response( $response );
		}

		$wc_customer_id = (int) str_replace( $this->prefix, '', $customer_id );
		$this->authorized_payments_processor->capture_authorized_payments_for_customer( $wc_customer_id );

		if(isset($request['resource']['id'])) {
			$this->logger->info("Setting token {$request['resource']['id']} for user {$wc_customer_id}");
			$this->payment_token_paypal->set_token($request['resource']['id']);
			$this->payment_token_paypal->set_user_id($wc_customer_id);
			$this->payment_token_paypal->save();
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
