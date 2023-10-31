<?php
/**
 * Check if payment token is saved and updates order accordingly.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Repository\OrderRepository;
use WooCommerce\PayPalCommerce\Subscription\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PaymentTokenChecker
 */
class PaymentTokenChecker {

	use FreeTrialHandlerTrait;

	const VAULTING_FAILED_META_KEY = '_ppcp_vaulting_failed';

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
	 * The payment token endpoint.
	 *
	 * @var PaymentTokenEndpoint
	 */
	protected $payment_token_endpoint;

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
	 * @param PaymentTokenEndpoint        $payment_token_endpoint The payment token endpoint.
	 * @param LoggerInterface             $logger The logger.
	 */
	public function __construct(
		PaymentTokenRepository $payment_token_repository,
		OrderRepository $order_repository,
		Settings $settings,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		PaymentsEndpoint $payments_endpoint,
		PaymentTokenEndpoint $payment_token_endpoint,
		LoggerInterface $logger
	) {
		$this->payment_token_repository      = $payment_token_repository;
		$this->order_repository              = $order_repository;
		$this->settings                      = $settings;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->payments_endpoint             = $payments_endpoint;
		$this->payment_token_endpoint        = $payment_token_endpoint;
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

		if ( $this->is_free_trial_order( $wc_order ) ) {
			if ( in_array( $wc_order->get_payment_method(), array( CreditCardGateway::ID, CardButtonGateway::ID ), true )
				|| ( PayPalGateway::ID === $wc_order->get_payment_method() && 'card' === $wc_order->get_meta( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY ) )
			) {
				$order = $this->order_repository->for_wc_order( $wc_order );
				$this->authorized_payments_processor->void_authorizations( $order );
				$wc_order->payment_complete();
			}

			return;
		}

		$tokens = $this->tokens_for_user( $customer_id );
		if ( $tokens ) {
			try {
				$this->capture_authorized_payment( $wc_order );
			} catch ( Exception $exception ) {
				$this->logger->warning( $exception->getMessage() );
			}

			return;
		}

		try {
			$subscription_behavior_when_fails = $this->settings->get( 'subscription_behavior_when_vault_fails' );
		} catch ( NotFoundException $exception ) {
			$subscription_behavior_when_fails = 'void_auth';
		}

		$wc_order->update_meta_data( self::VAULTING_FAILED_META_KEY, $subscription_behavior_when_fails );
		$wc_order->save_meta_data();

		switch ( $subscription_behavior_when_fails ) {
			case 'void_auth':
				$order = $this->order_repository->for_wc_order( $wc_order );
				$this->authorized_payments_processor->void_authorizations( $order );
				$this->logger->warning( "Payment for subscription parent order #{$order_id} was not saved at PayPal." );
				$this->update_failed_status( $wc_order );
				break;
			case 'capture_auth':
				try {
					$this->capture_authorized_payment( $wc_order );
				} catch ( Exception $exception ) {
					$this->logger->warning( $exception->getMessage() );
					return;
				}

				$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $wc_order ) : array();
				foreach ( $subscriptions as $subscription ) {
					try {
						$subscription->set_requires_manual_renewal( true );
						$subscription->save();

						$message = __( 'Subscription set to Manual Renewal because payment method was not saved at PayPal.', 'woocommerce-paypal-payments' );
						$wc_order->add_order_note( $message );

					} catch ( Exception $exception ) {
						$this->logger->warning( "Could not update payment method on subscription #{$subscription->get_id()} " . $exception->getMessage() );
					}
				}
				break;
			case 'capture_auth_ignore':
				try {
					$this->capture_authorized_payment( $wc_order );
				} catch ( Exception $exception ) {
					$this->logger->warning( $exception->getMessage() );
					return;
				}

				break;
		}
	}

	/**
	 * Schedules the vaulted payment check.
	 *
	 * @param int $wc_order_id The WC order ID.
	 * @param int $customer_id The customer ID.
	 */
	public function schedule_saved_payment_check( int $wc_order_id, int $customer_id ): void {
		$timestamp = 3 * MINUTE_IN_SECONDS;
		if (
			$this->settings->has( 'subscription_behavior_when_vault_fails' )
			&& $this->settings->get( 'subscription_behavior_when_vault_fails' ) === 'capture_auth'
		) {
			$timestamp = 0;
		}

		as_schedule_single_action(
			time() + $timestamp,
			'woocommerce_paypal_payments_check_saved_payment',
			array(
				'order_id'    => $wc_order_id,
				'customer_id' => $customer_id,
				'intent'      => $this->settings->has( 'intent' ) ? $this->settings->get( 'intent' ) : '',
			)
		);
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
		$error_message = __( 'Subscription payment failed. Payment method was not saved at PayPal.', 'woocommerce-paypal-payments' );
		$wc_order->update_status( 'failed', $error_message );

		/**
		 * Function already exist in WC Subscriptions plugin.
		 *
		 * @psalm-suppress UndefinedFunction
		 */
		$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $wc_order->get_id() ) : array();
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

	/**
	 * Returns customer tokens either from guest or customer id.
	 *
	 * @param int $customer_id The customer id.
	 * @return PaymentToken[]
	 */
	private function tokens_for_user( int $customer_id ): array {
		$tokens = array();

		$guest_customer_id = get_user_meta( $customer_id, 'ppcp_guest_customer_id', true );
		if ( $guest_customer_id ) {
			$tokens = $this->payment_token_endpoint->for_guest( $guest_customer_id );
		}

		if ( ! $tokens ) {
			$guest_customer_id = get_user_meta( $customer_id, 'ppcp_customer_id', true );
			if ( $guest_customer_id ) {
				$tokens = $this->payment_token_endpoint->for_guest( $guest_customer_id );
			}
		}

		if ( ! $tokens ) {
			$tokens = $this->payment_token_repository->all_for_user_id( $customer_id );
		}

		return $tokens;
	}
}
