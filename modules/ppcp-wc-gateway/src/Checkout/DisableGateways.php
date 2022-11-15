<?php
/**
 * Determines whether specific gateways need to be disabled.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Checkout
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Checkout;

use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

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
		if ( $this->disable_all_gateways() ) {
			unset( $methods[ PayPalGateway::ID ] );
			unset( $methods[ CreditCardGateway::ID ] );
			unset( $methods[ CardButtonGateway::ID ] );
			return $methods;
		}

		if ( ! $this->settings->has( 'client_id' ) || empty( $this->settings->get( 'client_id' ) ) ) {
			unset( $methods[ CreditCardGateway::ID ] );
		}

		if ( $this->settings->has( 'button_enabled' ) && ! $this->settings->get( 'button_enabled' ) && ! $this->session_handler->order() && is_checkout() ) {
			unset( $methods[ PayPalGateway::ID ] );
		}

		if ( ! $this->needs_to_disable_gateways() ) {
			return $methods;
		}

		return array( PayPalGateway::ID => $methods[ PayPalGateway::ID ] );
	}

	/**
	 * Whether all gateways should be disabled or not.
	 *
	 * @return bool
	 */
	private function disable_all_gateways() : bool {
		foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
			if ( PayPalGateway::ID === $gateway->id && $gateway->enabled !== 'yes' ) {
				return true;
			}
		}

		if ( ! $this->settings->has( 'merchant_email' ) || ! is_email( $this->settings->get( 'merchant_email' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the Gateways need to be disabled. When we come to the checkout with a running PayPal
	 * session, we need to disable the other Gateways, so the customer can smoothly sail through the
	 * process.
	 *
	 * @return bool
	 */
	private function needs_to_disable_gateways(): bool {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}

		$source = $order->payment_source();
		if ( $source && $source->card() ) {
			return false; // DCC.
		}

		if ( 'card' === $this->session_handler->funding_source() ) {
			return false; // Card buttons.
		}

		return true;
	}
}
