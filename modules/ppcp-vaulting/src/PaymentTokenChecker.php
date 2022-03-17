<?php
/**
 * Check if payment token is saved and updates order accordingly.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PaymentTokenChecker
 */
class PaymentTokenChecker {

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	protected $payment_token_repository;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * The authorized payments processor.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * The payments endpoint.
	 *
	 * @var PaymentsEndpoint
	 */
	protected $payments_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * PaymentTokenChecker constructor.
	 *
	 * @param PaymentTokenRepository      $payment_token_repository The payment token repository.
	 * @param Settings                    $settings The settings.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The authorized payments processor.
	 * @param OrderEndpoint               $order_endpoint The order endpoint.
	 * @param PaymentsEndpoint            $payments_endpoint The payments endpoint.
	 * @param LoggerInterface             $logger The logger.
	 */
	public function __construct(
		PaymentTokenRepository $payment_token_repository,
		Settings $settings,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		OrderEndpoint $order_endpoint,
		PaymentsEndpoint $payments_endpoint,
		LoggerInterface $logger
	) {
		$this->payment_token_repository      = $payment_token_repository;
		$this->settings                      = $settings;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->order_endpoint                = $order_endpoint;
		$this->payments_endpoint             = $payments_endpoint;
		$this->logger                        = $logger;
	}

	/**
	 * Check if payment token exist and updates order accordingly.
	 *
	 * @param int    $order_id The order ID.
	 * @param int    $customer_id The customer ID.
	 * @param string $intent The intent from settings when order was created.
	 * @return void
	 */
	public function check_and_update( int $order_id, int $customer_id, string $intent ):void {
		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, WC_Order::class ) ) {
			return;
		}

		if ( $wc_order->get_status() === 'processing' || 'capture' !== $intent ) {
			return;
		}

		$tokens = $this->payment_token_repository->all_for_user_id( $customer_id );
		if ( $tokens ) {
			try {
				$this->capture_authorized_payment( $wc_order );
			} catch ( Exception $exception ) {
				$this->logger->error( $exception->getMessage() );
			}

			return;
		}

		$this->logger->error( "Payment for subscription parent order #{$order_id} was not saved on PayPal." );

		try {
			$order = $this->get_order( $wc_order );
			$this->void_authorizations( $order );
		} catch ( RuntimeException $exception ) {
			$this->logger->warning( $exception->getMessage() );
		}

		$this->update_failed_status( $wc_order );
	}

	/**
	 * Captures authorized payments for the given WC order.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @throws Exception When there is a problem capturing the payment.
	 */
	private function capture_authorized_payment( WC_Order $wc_order ): void {
		if ( $this->settings->has( 'intent' ) && strtoupper( (string) $this->settings->get( 'intent' ) ) === 'CAPTURE' ) {
			if ( ! $this->authorized_payments_processor->capture_authorized_payment( $wc_order ) ) {
				throw new Exception( "Could not capture payment for order: #{$wc_order->get_id()}" );
			}
		}
	}

	/**
	 * Voids authorizations for the given PayPal order.
	 *
	 * @param Order $order The PayPal order.
	 * @return void
	 * @throws RuntimeException When there is a problem voiding authorizations.
	 */
	private function void_authorizations( Order $order ): void {
		$purchase_units = $order->purchase_units();
		if ( ! $purchase_units ) {
			throw new RuntimeException( 'No purchase units.' );
		}

		$payments = $purchase_units[0]->payments();
		if ( ! $payments ) {
			throw new RuntimeException( 'No payments.' );
		}

		$voidable_authorizations = array_filter(
			$payments->authorizations(),
			function ( Authorization $authorization ): bool {
				return $authorization->is_voidable();
			}
		);
		if ( ! $voidable_authorizations ) {
			throw new RuntimeException( 'No voidable authorizations.' );
		}

		foreach ( $voidable_authorizations as $authorization ) {
			$this->payments_endpoint->void( $authorization );
		}
	}

	/**
	 * Gets a PayPal order from the given WooCommerce order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return Order The PayPal order.
	 * @throws RuntimeException When there is a problem getting the PayPal order.
	 */
	private function get_order( WC_Order $wc_order ): Order {
		$paypal_order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		if ( ! $paypal_order_id ) {
			throw new RuntimeException( 'PayPal order ID not found in meta.' );
		}

		return $this->order_endpoint->order( $paypal_order_id );
	}

	/**
	 * Updates WC order and subscription status to failed and canceled respectively.
	 *
	 * @param WC_Order $wc_order The WC order.
	 */
	private function update_failed_status( WC_Order $wc_order ): void {
		$error_message = __( 'Could not process order because it was not possible to save the payment on PayPal.', 'woocommerce-paypal-payments' );
		$wc_order->update_status( 'failed', $error_message );

		/**
		 * Function already exist in Subscription plugin
		 *
		 * @psalm-suppress UndefinedFunction
		 */
		$subscriptions = wcs_get_subscriptions_for_order( $wc_order->get_id() );
		foreach ( $subscriptions as $key => $subscription ) {
			if ( $subscription->get_parent_id() === $wc_order->get_id() ) {
				try {
					$subscription->update_status( 'cancelled' );
					break;
				} catch ( Exception $exception ) {
					$this->logger->error( "Could not update cancelled status on subscription #{$subscription->get_id()} " . $exception->getMessage() );
				}
			}
		}
	}
}
