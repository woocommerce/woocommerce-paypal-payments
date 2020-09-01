<?php
/**
 * Determines whether specific gateways need to be disabled.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Checkout
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Checkout;

use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Psr\Container\ContainerInterface;

/**
 * Class DisableGateways
 */
class DisableGateways {


	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * DisableGateways constructor.
	 *
	 * @param SessionHandler     $session_handler The Session Handler.
	 * @param ContainerInterface $settings The Settings.
	 */
	public function __construct(
		SessionHandler $session_handler,
		ContainerInterface $settings
	) {

		$this->session_handler = $session_handler;
		$this->settings        = $settings;
	}

	/**
	 * Controls the logic for enabling/disabling gateways.
	 *
	 * @param array $methods The Gateways.
	 *
	 * @return array
	 */
	public function handler( array $methods ): array {
		if ( ! isset( $methods[ PayPalGateway::ID ] ) && ! isset( $methods[ CreditCardGateway::ID ] ) ) {
			return $methods;
		}
		if (
			! $this->settings->has( 'merchant_email' )
			|| ! is_email( $this->settings->get( 'merchant_email' ) )
		) {
			unset( $methods[ PayPalGateway::ID ] );
			unset( $methods[ CreditCardGateway::ID ] );
			return $methods;
		}

		if ( ! $this->settings->has( 'client_id' ) || empty( $this->settings->get( 'client_id' ) ) ) {
			unset( $methods[ CreditCardGateway::ID ] );
		}

		if ( ! $this->needs_to_disable_gateways() ) {
			return $methods;
		}

		if ( $this->is_credit_card() ) {
			return array( CreditCardGateway::ID => $methods[ CreditCardGateway::ID ] );
		}
		return array( PayPalGateway::ID => $methods[ PayPalGateway::ID ] );
	}

	/**
	 * Whether the Gateways need to be disabled. When we come to the checkout with a running PayPal
	 * session, we need to disable the other Gateways, so the customer can smoothly sail through the
	 * process.
	 *
	 * @return bool
	 */
	private function needs_to_disable_gateways(): bool {
		return $this->session_handler->order() !== null;
	}

	/**
	 * Whether the current PayPal session is done via DCC payment.
	 *
	 * @return bool
	 */
	private function is_credit_card(): bool {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}
		if ( ! $order->payment_source() || ! $order->payment_source()->card() ) {
			return false;
		}
		return true;
	}
}
