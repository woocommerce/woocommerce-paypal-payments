<?php
/**
 * Controls the endpoint for customers returning from PayPal.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PrefixTrait;

/**
 * Class ReturnUrlEndpoint
 */
class ReturnUrlEndpoint {

	use PrefixTrait;
	const ENDPOINT = 'ppc-return-url';

	/**
	 * The PayPal Gateway.
	 *
	 * @var PayPalGateway
	 */
	private $gateway;

	/**
	 * The Order Endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * ReturnUrlEndpoint constructor.
	 *
	 * @param PayPalGateway $gateway        The PayPal Gateway.
	 * @param OrderEndpoint $order_endpoint The Order Endpoint.
	 * @param string        $prefix                The prefix.
	 */
	public function __construct( PayPalGateway $gateway, OrderEndpoint $order_endpoint, string $prefix ) {
		$this->gateway        = $gateway;
		$this->order_endpoint = $order_endpoint;
		$this->prefix         = $prefix;
	}

	/**
	 * Handles the incoming request.
	 */
	public function handle_request() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['token'] ) ) {
			exit();
		}

		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$order = $this->order_endpoint->order( $token );
		if ( ! $order ) {
			exit();
		}

		$wc_order_id = $this->sanitize_custom_id( $order->purchase_units()[0]->custom_id() );
		if ( ! $wc_order_id ) {
			exit();
		}

		$wc_order = wc_get_order( $wc_order_id );
		if ( ! $wc_order ) {
			exit();
		}

		$success = $this->gateway->process_payment( $wc_order_id );
		if ( isset( $success['result'] ) && 'success' === $success['result'] ) {
			add_filter(
				'allowed_redirect_hosts',
				function( $allowed_hosts ) : array {
					$allowed_hosts[] = 'www.paypal.com';
					$allowed_hosts[] = 'www.sandbox.paypal.com';
					return (array) $allowed_hosts;
				}
			);
			wp_safe_redirect( $success['redirect'] );
			exit();
		}
		wp_safe_redirect( wc_get_checkout_url() );
		exit();
	}
}
