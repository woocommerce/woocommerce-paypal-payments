<?php
/**
 * The process_payment functionality for the both gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;

/**
 * Trait ProcessPaymentTrait
 */
trait ProcessPaymentTrait {
	/**
	 * Checks if PayPal or Credit Card gateways are enabled.
	 *
	 * @return bool Whether any of the gateways is enabled.
	 */
	protected function gateways_enabled(): bool {
		if ( $this->config->has( 'enabled' ) && $this->config->get( 'enabled' ) ) {
			return true;
		}
		if ( $this->config->has( 'dcc_enabled' ) && $this->config->get( 'dcc_enabled' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Scheduled the vaulted payment check.
	 *
	 * @param int $wc_order_id The WC order ID.
	 * @param int $customer_id The customer ID.
	 */
	protected function schedule_saved_payment_check( int $wc_order_id, int $customer_id ): void {
		$timestamp = 3 * MINUTE_IN_SECONDS;
		if (
			$this->config->has( 'subscription_behavior_when_vault_fails' )
			&& $this->config->get( 'subscription_behavior_when_vault_fails' ) === 'capture_auth'
		) {
			$timestamp = 0;
		}

		as_schedule_single_action(
			time() + $timestamp,
			'woocommerce_paypal_payments_check_saved_payment',
			array(
				'order_id'    => $wc_order_id,
				'customer_id' => $customer_id,
				'intent'      => $this->config->has( 'intent' ) ? $this->config->get( 'intent' ) : '',
			)
		);
	}

	/**
	 * Handles the payment failure.
	 *
	 * @param WC_Order|null $wc_order The order.
	 * @param Exception     $error The error causing the failure.
	 * @return array The data that can be returned by the gateway process_payment method.
	 */
	protected function handle_payment_failure( ?WC_Order $wc_order, Exception $error ): array {
		$this->logger->error( 'Payment failed: ' . $this->format_exception( $error ) );

		if ( $wc_order ) {
			$wc_order->update_status(
				'failed',
				$this->format_exception( $error )
			);
		}

		$this->session_handler->destroy_session_data();

		wc_add_notice( $error->getMessage(), 'error' );

		return array(
			'result'       => 'failure',
			'redirect'     => wc_get_checkout_url(),
			'errorMessage' => $error->getMessage(),
		);
	}

	/**
	 * Handles the payment completion.
	 *
	 * @param WC_Order|null $wc_order The order.
	 * @param string|null   $url The redirect URL.
	 * @return array The data that can be returned by the gateway process_payment method.
	 */
	protected function handle_payment_success( ?WC_Order $wc_order, string $url = null ): array {
		if ( ! $url ) {
			$url = $this->get_return_url( $wc_order );
		}

		$this->session_handler->destroy_session_data();

		return array(
			'result'   => 'success',
			'redirect' => $url,
		);
	}

	/**
	 * Outputs the exception, including the inner exception.
	 *
	 * @param Throwable $exception The exception to format.
	 * @return string
	 */
	protected function format_exception( Throwable $exception ): string {
		$output = $exception->getMessage() . ' ' . basename( $exception->getFile() ) . ':' . $exception->getLine();
		$prev   = $exception->getPrevious();
		if ( ! $prev ) {
			return $output;
		}
		if ( $exception instanceof GatewayGenericException ) {
			$output = '';
		}
		return $output . ' ' . $this->format_exception( $prev );
	}
}
