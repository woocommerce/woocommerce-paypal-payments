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
use WooCommerce\PayPalCommerce\ApiClient\Exception\AlreadyVaultedException;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = wc_clean( wp_unslash( $_GET['approval_token_id'] ?? '' ) );
		if ( ! $token || is_array( $token ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$url = (string) filter_var( $_SERVER['REQUEST_URI'] ?? '', FILTER_SANITIZE_URL );

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $query && str_contains( $query, 'ppcp_vault=cancel' ) ) {
			$this->redirect( $url );
			return;
		}

		try {
			$this->payment_token_endpoint->create_from_approval_token( $token, get_current_user_id() );

			$this->redirect( $url );
		} catch ( AlreadyVaultedException $exception ) {
			$this->logger->error( 'Failed to create payment token. ' . $exception->getMessage() );
			$this->add_wc_error_notice(
				__(
					'This PayPal account is already saved on this site. Please check that you are logged in correctly.',
					'woocommerce-paypal-payments'
				)
			);
		} catch ( Exception $exception ) {
			$this->logger->error( 'Failed to create payment token. ' . $exception->getMessage() );
			$this->add_wc_error_notice( $exception->getMessage() );
		}
	}

	/**
	 * Makes the message to be added on the WC init event.
	 *
	 * @param string $message The message text.
	 */
	private function add_wc_error_notice( string $message ): void {
		add_action(
			'woocommerce_init',
			function () use ( $message ): void {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $message, 'error' );
				}
			}
		);
	}

	/**
	 * Redirects removing the vaulting arguments.
	 *
	 * @param string $current_url The current request URL.
	 */
	private function redirect( string $current_url ): void {
		wp_safe_redirect( remove_query_arg( array( 'ppcp_vault', 'approval_token_id', 'approval_session_id' ), $current_url ) );
		exit();
	}
}
