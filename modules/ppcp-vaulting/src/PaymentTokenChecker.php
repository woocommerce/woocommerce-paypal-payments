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
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Repository\OrderRepository;
use WooCommerce\PayPalCommerce\Subscription\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PaymentTokenChecker
 */
class PaymentTokenChecker {

	use FreeTrialHandlerTrait;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	protected $payment_token_repository;

	/**
	 * The order repository.
	 *
	 * @var OrderRepository
	 */
	protected $order_repository;

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
	 * @param OrderRepository             $order_repository The order repository.
	 * @param Settings                    $settings The settings.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The authorized payments processor.
	 * @param PaymentsEndpoint            $payments_endpoint The payments endpoint.
	 * @param LoggerInterface             $logger The logger.
	 */
	public function __construct(
		PaymentTokenRepository $payment_token_repository,
		OrderRepository $order_repository,
		Settings $settings,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		PaymentsEndpoint $payments_endpoint,
		LoggerInterface $logger
	) {
		$this->payment_token_repository      = $payment_token_repository;
		$this->order_repository              = $order_repository;
		$this->settings                      = $settings;
		$this->authorized_payments_processor = $authorized_payments_processor;
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
				if ( $this->is_free_trial_order( $wc_order ) ) {
					if ( CreditCardGateway::ID === $wc_order->get_payment_method()
						|| ( PayPalGateway::ID === $wc_order->get_payment_method() && 'card' === $wc_order->get_meta( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY ) )
					) {
						$order = $this->order_repository->for_wc_order( $wc_order );
						$this->authorized_payments_processor->void_authorizations( $order );
						$wc_order->payment_complete();
					}

					return;
				}

				$this->capture_authorized_payment( $wc_order );
			} catch ( Exception $exception ) {
				$this->logger->error( $exception->getMessage() );
			}

			return;
		}

		$this->logger->error( "Payment for subscription parent order #{$order_id} was not saved on PayPal." );

		try {
			$order = $this->order_repository->for_wc_order( $wc_order );
			$this->authorized_payments_processor->void_authorizations( $order );
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
