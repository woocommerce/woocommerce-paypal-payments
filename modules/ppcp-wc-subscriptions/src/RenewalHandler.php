<?php
/**
 * Handles subscription renewals.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use WC_Customer;
use WC_Order;
use WC_Payment_Tokens;
use WC_Subscription;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

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
	 * The shipping_preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	private $shipping_preference_factory;

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
	 * The settings
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * The processor for authorized payments.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * RenewalHandler constructor.
	 *
	 * @param LoggerInterface             $logger The logger.
	 * @param PaymentTokenRepository      $repository The payment token repository.
	 * @param OrderEndpoint               $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory         $purchase_unit_factory The purchase unit factory.
	 * @param ShippingPreferenceFactory   $shipping_preference_factory The shipping_preference factory.
	 * @param PayerFactory                $payer_factory The payer factory.
	 * @param Environment                 $environment The environment.
	 * @param Settings                    $settings The Settings.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments Processor.
	 */
	public function __construct(
		LoggerInterface $logger,
		PaymentTokenRepository $repository,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		PayerFactory $payer_factory,
		Environment $environment,
		Settings $settings,
		AuthorizedPaymentsProcessor $authorized_payments_processor
	) {

		$this->logger                        = $logger;
		$this->repository                    = $repository;
		$this->order_endpoint                = $order_endpoint;
		$this->purchase_unit_factory         = $purchase_unit_factory;
		$this->shipping_preference_factory   = $shipping_preference_factory;
		$this->payer_factory                 = $payer_factory;
		$this->environment                   = $environment;
		$this->settings                      = $settings;
		$this->authorized_payments_processor = $authorized_payments_processor;
	}

	/**
	 * Renew an order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 */
	public function renew( \WC_Order $wc_order ): void {
		try {
			$subscription = wcs_get_subscription( $wc_order->get_id() );
			if ( is_a( $subscription, WC_Subscription::class ) ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					return;
				}
			}

			$this->process_order( $wc_order );
		} catch ( \Exception $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$wc_order->update_status(
				'failed',
				$error
			);

			$error_message = sprintf(
				'An error occurred while trying to renew the subscription for order %1$d: %2$s',
				$wc_order->get_id(),
				$error
			);
			$this->logger->error( $error_message );

			return;
		}
	}

	/**
	 * Process a WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @throws \Exception If customer cannot be read/found.
	 */
	private function process_order( \WC_Order $wc_order ): void {
		$user_id  = (int) $wc_order->get_customer_id();
		$customer = new \WC_Customer( $user_id );

		$purchase_unit       = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payer               = $this->payer_factory->from_customer( $customer );
		$shipping_preference = $this->shipping_preference_factory->from_state(
			$purchase_unit,
			'renewal'
		);

		$token_id = $this->token_id( $wc_order, $customer );
		if ( $token_id ) {
			if ( $wc_order->get_payment_method() === CreditCardGateway::ID ) {
				$stored_credentials = array(
					'payment_initiator' => 'MERCHANT',
					'payment_type'      => 'RECURRING',
					'usage'             => 'SUBSEQUENT',
				);

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $wc_order );
				foreach ( $subscriptions as $post_id => $subscription ) {
					$previous_transaction_reference = $subscription->get_meta( 'ppcp_previous_transaction_reference' );
					if ( $previous_transaction_reference ) {
						$stored_credentials['previous_transaction_reference'] = $previous_transaction_reference;
						break;
					}
				}

				$payment_source = new PaymentSource(
					'card',
					(object) array(
						'vault_id'          => $token_id,
						'stored_credential' => $stored_credentials,
					)
				);

				$order = $this->order_endpoint->create(
					array( $purchase_unit ),
					$shipping_preference,
					$payer,
					null,
					'',
					ApplicationContext::USER_ACTION_CONTINUE,
					'',
					array(),
					$payment_source
				);

				$this->handle_paypal_order( $wc_order, $order );

				$this->logger->info(
					sprintf(
						'Renewal for order %d is completed.',
						$wc_order->get_id()
					)
				);

				return;
			}

			$payment_source = new PaymentSource(
				'paypal',
				(object) array(
					'vault_id' => $token_id,
				)
			);

			$order = $this->order_endpoint->create(
				array( $purchase_unit ),
				$shipping_preference,
				$payer,
				null,
				'',
				ApplicationContext::USER_ACTION_CONTINUE,
				'',
				array(),
				$payment_source
			);

			$this->handle_paypal_order( $wc_order, $order );

			$this->logger->info(
				sprintf(
					'Renewal for order %d is completed.',
					$wc_order->get_id()
				)
			);

			return;
		}

		if ( apply_filters( 'woocommerce_paypal_payments_subscription_renewal_return_before_create_order_without_token', true ) ) {
			return;
		}

		$order = $this->order_endpoint->create(
			array( $purchase_unit ),
			$shipping_preference,
			$payer
		);

		$this->handle_paypal_order( $wc_order, $order );

		$this->logger->info(
			sprintf(
				'Renewal for order %d is completed.',
				$wc_order->get_id()
			)
		);
	}

	/**
	 * Returns a payment token for a customer.
	 *
	 * @param \WC_Customer $customer The customer.
	 * @param \WC_Order    $wc_order The current WooCommerce order we want to process.
	 *
	 * @return PaymentToken|null|false
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
			$token_id = $subscription->get_meta( 'payment_token_id' );
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

	/**
	 * Returns if an order should be captured immediately.
	 *
	 * @param Order $order The PayPal order.
	 *
	 * @return bool
	 * @throws NotFoundException When a setting was not found.
	 */
	protected function capture_authorized_downloads( Order $order ): bool {
		if (
			! $this->settings->has( 'capture_for_virtual_only' )
			|| ! $this->settings->get( 'capture_for_virtual_only' )
		) {
			return false;
		}

		if ( $order->intent() === 'CAPTURE' ) {
			return false;
		}

		/**
		 * We fetch the order again as the authorize endpoint (from which the Order derives)
		 * drops the item's category, making it impossible to check, if purchase units contain
		 * physical goods.
		 */
		$order = $this->order_endpoint->order( $order->id() );

		foreach ( $order->purchase_units() as $unit ) {
			if ( $unit->contains_physical_goods() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Handles PayPal order creation and updates WC order accordingly.
	 *
	 * @param \WC_Order $wc_order WC order.
	 * @param Order     $order PayPal order.
	 * @return void
	 * @throws NotFoundException When something goes wrong while handling the order.
	 */
	private function handle_paypal_order( \WC_Order $wc_order, Order $order ): void {
		$this->add_paypal_meta( $wc_order, $order, $this->environment );

		if ( $order->intent() === 'AUTHORIZE' ) {
			$order = $this->order_endpoint->authorize( $order );
			$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );
		}

		$transaction_id = $this->get_paypal_order_transaction_id( $order );
		if ( $transaction_id ) {
			$this->update_transaction_id( $transaction_id, $wc_order );

			$subscriptions = wcs_get_subscriptions_for_order( $wc_order->get_id(), array( 'order_type' => 'any' ) );
			foreach ( $subscriptions as $id => $subscription ) {
				$subscription->update_meta_data( 'ppcp_previous_transaction_reference', $transaction_id );
				$subscription->save();
			}
		}

		$this->handle_new_order_status( $order, $wc_order );

		if ( $this->capture_authorized_downloads( $order ) ) {
			$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
		}
	}

	/**
	 * Returns a payment token id for the given order or customer.
	 *
	 * @param WC_Order    $wc_order WC order.
	 * @param WC_Customer $customer WC customer.
	 * @return string
	 */
	private function token_id( WC_Order $wc_order, WC_Customer $customer ): string {
		$token_id = '';

		$tokens = $wc_order->get_payment_tokens();
		if ( $tokens ) {
			foreach ( $tokens as $token_id ) {
				$token = WC_Payment_Tokens::get( $token_id );
				if ( $token && $token->get_gateway_id() === $wc_order->get_payment_method() ) {
					return $token->get_token();
				}
			}
		}

		if ( ! $token_id ) {
			$token    = $this->get_token_for_customer( $customer, $wc_order );
			$token_id = $token->id();
		}

		return $token_id;
	}
}
