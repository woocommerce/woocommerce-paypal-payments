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
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

class PaymentTokenChecker {

	/**
	 * @var PaymentTokenRepository
	 */
	protected $payment_token_repository;

	/**
	 * @var Settings
	 */
	protected $settings;

	/**
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * @var PaymentsEndpoint
	 */
	protected $payments_endpoint;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		PaymentTokenRepository $payment_token_repository,
		Settings $settings,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		OrderEndpoint $order_endpoint,
		PaymentsEndpoint $payments_endpoint,
		LoggerInterface $logger
	)
	{
		$this->payment_token_repository = $payment_token_repository;
		$this->settings = $settings;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->order_endpoint = $order_endpoint;
		$this->logger = $logger;
		$this->payments_endpoint = $payments_endpoint;
	}

	public function checkAndUpdate($order_id, $customer_id) {
		$tokens = $this->payment_token_repository->all_for_user_id( $customer_id );
		if ( $tokens ) {
			$this->capture_authorized_payment(
				$order_id,
				$customer_id
			);
		}

		$this->logger->error( "Payment for subscription parent order #{$order_id} was not saved on PayPal." );

		$wc_order = wc_get_order( $order_id );
		$order    = $this->getOrder( $wc_order );

		try {
			$this->void_authorizations( $order);
		} catch ( RuntimeException $exception ) {
			$this->logger->warning($exception->getMessage());
		}

		$this->updateFailedStatus( $wc_order, $order_id );
	}

	/**
	 * @param $order_id
	 * @param $customer_id
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException
	 */
	private function capture_authorized_payment($order_id, $customer_id): void {
		if ( $this->settings->has( 'intent' ) && strtoupper( (string) $this->settings->get( 'intent' ) ) === 'CAPTURE' ) {
			$wc_order = wc_get_order( $order_id );
			$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
			$this->logger->info( "Order: #{$order_id} for user: {$customer_id} captured successfully." );
		}
	}

	/**
	 * @param $order
	 * @param $payments_endpoint
	 * @throws RuntimeException
	 */
	protected function void_authorizations( $order ): void {
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
	 * @param $wc_order
	 * @return mixed
	 */
	protected function getOrder( $wc_order ) {
		$paypal_order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		if ( ! $paypal_order_id ) {
			throw new RuntimeException( 'PayPal order ID not found in meta.' );
		}

		return $this->order_endpoint->order( $paypal_order_id );
	}

	/**
	 * @param $wc_order
	 * @param $order_id
	 */
	protected function updateFailedStatus( $wc_order, $order_id ): void {
		$error_message = __( 'Could not process order because it was not possible to save the payment on PayPal.', 'woocommerce-paypal-payments' );
		$wc_order->update_status( 'failed', $error_message );

		$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		foreach ( $subscriptions as $key => $subscription ) {
			if ( $subscription->get_parent_id() === $order_id ) {
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
