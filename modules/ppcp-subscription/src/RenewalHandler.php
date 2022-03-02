<?php
/**
 * Handles subscription renewals.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;

/**
 * Class RenewalHandler
 */
class RenewalHandler {

	use OrderMetaTrait;
	use TransactionIdHandlingTrait;
	use PaymentsStatusHandlingTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $repository;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The payer factory.
	 *
	 * @var PayerFactory
	 */
	private $payer_factory;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * RenewalHandler constructor.
	 *
	 * @param LoggerInterface        $logger The logger.
	 * @param PaymentTokenRepository $repository The payment token repository.
	 * @param OrderEndpoint          $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory    $purchase_unit_factory The purchase unit factory.
	 * @param PayerFactory           $payer_factory The payer factory.
	 * @param Environment            $environment The environment.
	 */
	public function __construct(
		LoggerInterface $logger,
		PaymentTokenRepository $repository,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		PayerFactory $payer_factory,
		Environment $environment
	) {

		$this->logger                = $logger;
		$this->repository            = $repository;
		$this->order_endpoint        = $order_endpoint;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->payer_factory         = $payer_factory;
		$this->environment           = $environment;
	}

	/**
	 * Renew an order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 */
	public function renew( \WC_Order $wc_order ) {
		try {
			$this->process_order( $wc_order );
		} catch ( \Exception $error ) {
			$this->logger->error(
				sprintf(
					'An error occurred while trying to renew the subscription for order %1$d: %2$s',
					$wc_order->get_id(),
					$error->getMessage()
				)
			);

			return;
		}
		$this->logger->info(
			sprintf(
				'Renewal for order %d is completed.',
				$wc_order->get_id()
			)
		);
	}

	/**
	 * Process a WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @throws \Exception If customer cannot be read/found.
	 */
	private function process_order( \WC_Order $wc_order ) {

		$user_id  = (int) $wc_order->get_customer_id();
		$customer = new \WC_Customer( $user_id );
		$token    = $this->get_token_for_customer( $customer, $wc_order );
		if ( ! $token ) {
			return;
		}
		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payer         = $this->payer_factory->from_customer( $customer );

		$order = $this->order_endpoint->create(
			array( $purchase_unit ),
			$payer,
			$token
		);

		$this->add_paypal_meta( $wc_order, $order, $this->environment );

		if ( $order->intent() === 'AUTHORIZE' ) {
			$order = $this->order_endpoint->authorize( $order );
			$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );
		}

		$transaction_id = $this->get_paypal_order_transaction_id( $order );
		if ( $transaction_id ) {
			$this->update_transaction_id( $transaction_id, $wc_order );
		}

		$this->handle_new_order_status( $order, $wc_order );
	}

	/**
	 * Returns a payment token for a customer.
	 *
	 * @param \WC_Customer $customer The customer.
	 * @param \WC_Order    $wc_order The current WooCommerce order we want to process.
	 *
	 * @return PaymentToken|null
	 */
	private function get_token_for_customer( \WC_Customer $customer, \WC_Order $wc_order ) {
		/**
		 * Returns a payment token for a customer, or null.
		 */
		$token = apply_filters( 'woocommerce_paypal_payments_subscriptions_get_token_for_customer', null, $customer, $wc_order );
		if ( null !== $token ) {
			return $token;
		}

		$tokens = $this->repository->all_for_user_id( (int) $customer->get_id() );
		if ( ! $tokens ) {

			$error_message = sprintf(
				'Payment failed. No payment tokens found for customer %d.',
				$customer->get_id()
			);

			$wc_order->update_status(
				'failed',
				$error_message
			);

			$this->logger->error( $error_message );
		}

		$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $wc_order->get_meta( '_subscription_renewal' ) ) : null;
		if ( $subscription ) {
			$subscription_id = $subscription->get_id();
			$token_id        = get_post_meta( $subscription_id, 'payment_token_id', true );
			if ( $token_id ) {
				foreach ( $tokens as $token ) {
					if ( $token_id === $token->id() ) {
						return $token;
					}
				}
			}
		}

		return current( $tokens );
	}
}
