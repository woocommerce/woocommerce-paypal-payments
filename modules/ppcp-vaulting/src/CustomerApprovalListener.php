<?php
/**
 * Confirm approval token after the PayPal vaulting approval by customer (v2/vault/payment-tokens with CUSTOMER_ACTION_REQUIRED response).
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\Subscription\FreeTrialHandlerTrait;

/**
 * Class CustomerApprovalListener
 */
class CustomerApprovalListener {

	use FreeTrialHandlerTrait;

	/**
	 * The PaymentTokenEndpoint.
	 *
	 * @var PaymentTokenEndpoint
	 */
	private $payment_token_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * CustomerApprovalListener constructor.
	 *
	 * @param PaymentTokenEndpoint $payment_token_endpoint The PaymentTokenEndpoint.
	 * @param LoggerInterface      $logger The logger.
	 */
	public function __construct( PaymentTokenEndpoint $payment_token_endpoint, LoggerInterface $logger ) {
		$this->payment_token_endpoint = $payment_token_endpoint;
		$this->logger                 = $logger;
	}

	/**
	 * Listens for redirects after the PayPal vaulting approval by customer.
	 *
	 * @return void
	 */
	public function listen(): void {
		$token = filter_input( INPUT_GET, 'approval_token_id', FILTER_SANITIZE_STRING );
		if ( ! is_string( $token ) ) {
			return;
		}

		$url = (string) filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );

		try {
			$query = wp_parse_url( $url, PHP_URL_QUERY );
			if ( $query && str_contains( $query, 'ppcp_vault=cancel' ) ) {
				return;
			}

			try {
				$this->payment_token_endpoint->create_from_approval_token( $token, get_current_user_id() );
			} catch ( Exception $exception ) {
				$this->logger->error( 'Failed to create payment token. ' . $exception->getMessage() );
			}
		} finally {
			wp_safe_redirect( remove_query_arg( array( 'ppcp_vault', 'approval_token_id', 'approval_session_id' ), $url ) );
			exit();
		}
	}
}
