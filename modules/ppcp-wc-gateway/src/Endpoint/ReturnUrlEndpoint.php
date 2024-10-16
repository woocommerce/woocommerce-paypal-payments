<?php
/**
 * Controls the endpoint for customers returning from PayPal.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class ReturnUrlEndpoint
 */
class ReturnUrlEndpoint {

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
	 * The session handler
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * ReturnUrlEndpoint constructor.
	 *
	 * @param PayPalGateway   $gateway         The PayPal Gateway.
	 * @param OrderEndpoint   $order_endpoint  The Order Endpoint.
	 * @param SessionHandler  $session_handler The session handler.
	 * @param LoggerInterface $logger          The logger.
	 */
	public function __construct(
		PayPalGateway $gateway,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler,
		LoggerInterface $logger
	) {
		$this->gateway         = $gateway;
		$this->order_endpoint  = $order_endpoint;
		$this->session_handler = $session_handler;
		$this->logger          = $logger;
	}

	/**
	 * Handles the incoming request.
	 */
	public function handle_request(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['token'] ) ) {
			exit();
		}

		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$order = $this->order_endpoint->order( $token );

		if ( $order->status()->is( OrderStatus::APPROVED )
			|| $order->status()->is( OrderStatus::COMPLETED )
		) {
			$this->session_handler->replace_order( $order );
		}

		$wc_order_id = (int) $order->purchase_units()[0]->custom_id();
		if ( ! $wc_order_id ) {
			// We cannot finish processing here without WC order, but at least go into the continuation mode.
			if ( $order->status()->is( OrderStatus::APPROVED )
				|| $order->status()->is( OrderStatus::COMPLETED )
			) {
				wp_safe_redirect( wc_get_checkout_url() );
				exit();
			}

			$this->logger->warning( "Return URL endpoint $token: no WC order ID." );
			exit();
		}

		$wc_order = wc_get_order( $wc_order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			$this->logger->warning( "Return URL endpoint $token: WC order $wc_order_id not found." );
			exit();
		}

		if ( $wc_order->get_payment_method() === OXXOGateway::ID ) {
			$this->session_handler->destroy_session_data();
			wp_safe_redirect( wc_get_checkout_url() );
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
