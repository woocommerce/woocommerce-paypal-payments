<?php
/**
 * Handles the buyer returning from a PayPal vault-without-purchase approval,
 * started from the native "Place order" button on a $0 free-trial subscription.
 *
 * The setup token was approved at PayPal; here it is exchanged for a stored
 * payment token and the pending WC order is completed by re-running the gateway.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class FreeTrialVaultReturnEndpoint
 */
class FreeTrialVaultReturnEndpoint {

	const ENDPOINT = 'ppc-free-trial-vault-return';

	/**
	 * The order meta key holding the approved setup token id.
	 */
	const SETUP_TOKEN_META = '_ppcp_free_trial_setup_token';

	/**
	 * The order meta key holding the one-time return nonce.
	 */
	const RETURN_NONCE_META = '_ppcp_free_trial_vault_nonce';

	/**
	 * The payment method tokens endpoint.
	 *
	 * @var PaymentMethodTokensEndpoint
	 */
	private $payment_method_tokens_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * FreeTrialVaultReturnEndpoint constructor.
	 *
	 * @param PaymentMethodTokensEndpoint $payment_method_tokens_endpoint The payment method tokens endpoint.
	 * @param LoggerInterface             $logger                         The logger.
	 */
	public function __construct(
		PaymentMethodTokensEndpoint $payment_method_tokens_endpoint,
		LoggerInterface $logger
	) {
		$this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
		$this->logger                         = $logger;
	}

	/**
	 * Handles the incoming return request.
	 */
	public function handle_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$wc_order_id = isset( $_GET['ppcp_vault_wc_order'] ) ? absint( wp_unslash( $_GET['ppcp_vault_wc_order'] ) ) : 0;

		// wp_unslash() can return an array, so the value is sanitized on the next line behind an is_string() guard.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$provided_nonce = wp_unslash( $_GET['ppcp_vault_nonce'] ?? '' );
		$provided_nonce = is_string( $provided_nonce ) ? sanitize_text_field( $provided_nonce ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$wc_order = $wc_order_id ? wc_get_order( $wc_order_id ) : false;
		if ( ! ( $wc_order instanceof WC_Order ) ) {
			$this->fail( __( 'Order not found. Please try placing your order again.', 'woocommerce-paypal-payments' ) );
			return;
		}

		// The order id arrives from a public, guessable query argument; require the
		// one-time nonce stored on the order to match before any handling, so a
		// hand-crafted return URL cannot trigger a token exchange.
		$stored_nonce = (string) $wc_order->get_meta( self::RETURN_NONCE_META );
		if ( ! $provided_nonce || ! $stored_nonce || ! hash_equals( $stored_nonce, $provided_nonce ) ) {
			$this->logger->warning( "Free-trial vault return: nonce mismatch for WC order {$wc_order_id}." );
			$this->fail( __( 'Payment session expired. Please try placing your order again.', 'woocommerce-paypal-payments' ) );
			return;
		}

		$setup_token_id = (string) $wc_order->get_meta( self::SETUP_TOKEN_META );
		if ( ! $setup_token_id ) {
			$this->fail( __( 'Payment information is missing. Please try placing your order again.', 'woocommerce-paypal-payments' ) );
			return;
		}

		$customer_id = is_user_logged_in()
			? (string) get_user_meta( get_current_user_id(), '_ppcp_target_customer_id', true )
			: '';

		try {
			/**
			 * Suppress ArgumentTypeCoercion
			 *
			 * @psalm-suppress ArgumentTypeCoercion
			 */
			$payment_source = new PaymentSource(
				'token',
				(object) array(
					'id'   => $setup_token_id,
					'type' => 'SETUP_TOKEN',
				)
			);

			$result = $this->payment_method_tokens_endpoint->create_payment_token( $payment_source, $customer_id );
		} catch ( Exception $exception ) {
			$this->logger->error( "Free-trial vault return: could not exchange setup token for WC order {$wc_order_id}: " . $exception->getMessage() );
			$this->fail( __( 'Could not save the PayPal account. Please try again.', 'woocommerce-paypal-payments' ) );
			return;
		}

		// One-time use: drop the stored token/nonce so the return URL cannot be replayed.
		$wc_order->delete_meta_data( self::SETUP_TOKEN_META );
		$wc_order->delete_meta_data( self::RETURN_NONCE_META );
		$wc_order->save();

		// Hand the exchanged token to the gateway's existing free-trial branch, which
		// stores it against the customer and completes the $0 order. Works for both
		// logged-in and guest buyers (the branch keys off the order's customer id).
		WC()->session->set( 'ppcp_guest_payment_for_free_trial', $result );

		$gateway = $this->paypal_gateway();
		if ( ! $gateway ) {
			$this->fail( __( 'Payment gateway is unavailable. Please try again or contact support.', 'woocommerce-paypal-payments' ) );
			return;
		}

		try {
			$success = $gateway->process_payment( $wc_order_id );
		} catch ( Exception $exception ) {
			$this->logger->error( "Free-trial vault return: process_payment failed for WC order {$wc_order_id}: " . $exception->getMessage() );
			$this->fail( __( 'Payment processing failed. Please try again or contact support.', 'woocommerce-paypal-payments' ) );
			return;
		}

		if ( isset( $success['result'] ) && 'success' === $success['result'] && isset( $success['redirect'] ) ) {
			wp_safe_redirect( $success['redirect'] );
			exit();
		}

		$this->fail( __( 'Payment processing failed. Please try again or contact support.', 'woocommerce-paypal-payments' ) );
	}

	/**
	 * Resolves the PayPal gateway from the registered WooCommerce gateways.
	 *
	 * @return PayPalGateway|null
	 */
	private function paypal_gateway(): ?PayPalGateway {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();

		$gateway = $gateways[ PayPalGateway::ID ] ?? null;

		return $gateway instanceof PayPalGateway ? $gateway : null;
	}

	/**
	 * Adds a checkout notice and redirects back to the checkout.
	 *
	 * @param string $message The error message.
	 */
	private function fail( string $message ): void {
		wc_add_notice( $message, 'error' );
		wp_safe_redirect( wc_get_checkout_url() );
		exit();
	}
}
