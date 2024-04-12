<?php
/**
 * Handles subscription renewals.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Tokens;
use WC_Subscription;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenApplePay;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\RealTimeAccountUpdaterHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

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
	 * The funding source renderer.
	 *
	 * @var FundingSourceRenderer
	 */
	protected $funding_source_renderer;

	/**
	 * Real Time Account Updater helper.
	 *
	 * @var RealTimeAccountUpdaterHelper
	 */
	private $real_time_account_updater_helper;

	/**
	 * Subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	private $subscription_helper;

	/**
	 * Payment tokens endpoint
	 *
	 * @var PaymentTokensEndpoint
	 */
	private $payment_tokens_endpoint;

	/**
	 * WooCommerce payments tokens factory.
	 *
	 * @var WooCommercePaymentTokens
	 */
	private $wc_payment_tokens;

	/**
	 * RenewalHandler constructor.
	 *
	 * @param LoggerInterface              $logger The logger.
	 * @param PaymentTokenRepository       $repository The payment token repository.
	 * @param OrderEndpoint                $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory          $purchase_unit_factory The purchase unit factory.
	 * @param ShippingPreferenceFactory    $shipping_preference_factory The shipping_preference factory.
	 * @param PayerFactory                 $payer_factory The payer factory.
	 * @param Environment                  $environment The environment.
	 * @param Settings                     $settings The Settings.
	 * @param AuthorizedPaymentsProcessor  $authorized_payments_processor The Authorized Payments Processor.
	 * @param FundingSourceRenderer        $funding_source_renderer The funding source renderer.
	 * @param RealTimeAccountUpdaterHelper $real_time_account_updater_helper Real Time Account Updater helper.
	 * @param SubscriptionHelper           $subscription_helper Subscription helper.
	 * @param PaymentTokensEndpoint        $payment_tokens_endpoint Payment tokens endpoint.
	 * @param WooCommercePaymentTokens     $wc_payment_tokens WooCommerce payments tokens factory.
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
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		FundingSourceRenderer $funding_source_renderer,
		RealTimeAccountUpdaterHelper $real_time_account_updater_helper,
		SubscriptionHelper $subscription_helper,
		PaymentTokensEndpoint $payment_tokens_endpoint,
		WooCommercePaymentTokens $wc_payment_tokens
	) {

		$this->logger                           = $logger;
		$this->repository                       = $repository;
		$this->order_endpoint                   = $order_endpoint;
		$this->purchase_unit_factory            = $purchase_unit_factory;
		$this->shipping_preference_factory      = $shipping_preference_factory;
		$this->payer_factory                    = $payer_factory;
		$this->environment                      = $environment;
		$this->settings                         = $settings;
		$this->authorized_payments_processor    = $authorized_payments_processor;
		$this->funding_source_renderer          = $funding_source_renderer;
		$this->real_time_account_updater_helper = $real_time_account_updater_helper;
		$this->subscription_helper              = $subscription_helper;
		$this->payment_tokens_endpoint          = $payment_tokens_endpoint;
		$this->wc_payment_tokens                = $wc_payment_tokens;
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

		// Vault v3.
		$payment_source = null;
		if ( $wc_order->get_payment_method() === PayPalGateway::ID ) {
			$customer_tokens = $this->wc_payment_tokens->customer_tokens( $user_id );

			$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, PayPalGateway::ID );

			if ( $customer_tokens && empty( $wc_tokens ) ) {
				$this->wc_payment_tokens->create_wc_tokens( $customer_tokens, $user_id );
			}

			$customer_token_ids = array();
			foreach ( $customer_tokens as $customer_token ) {
				$customer_token_ids[] = $customer_token['id'];
			}

			$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, PayPalGateway::ID );
			foreach ( $wc_tokens as $token ) {
				if ( ! in_array( $token->get_token(), $customer_token_ids, true ) ) {
					$token->delete();
					continue;
				}

				$name       = 'paypal';
				$properties = array(
					'vault_id' => $token->get_token(),
				);

				if ( $token instanceof PaymentTokenPayPal ) {
					$name = 'paypal';
				}

				if ( $token instanceof PaymentTokenVenmo ) {
					$name = 'venmo';
				}

				if ( $token instanceof PaymentTokenApplePay ) {
					$name                            = 'apple_pay';
					$properties['stored_credential'] = array(
						'payment_initiator' => 'MERCHANT',
						'payment_type'      => 'RECURRING',
						'usage'             => 'SUBSEQUENT',
					);
				}

				$payment_source = new PaymentSource(
					$name,
					(object) $properties
				);

				break;
			}
		}

		if ( $wc_order->get_payment_method() === CreditCardGateway::ID ) {
			$customer_tokens = $this->wc_payment_tokens->customer_tokens( $user_id );

			$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, CreditCardGateway::ID );

			if ( $customer_tokens && empty( $wc_tokens ) ) {
				$this->wc_payment_tokens->create_wc_tokens( $customer_tokens, $user_id );
			}

			$customer_token_ids = array();
			foreach ( $customer_tokens as $customer_token ) {
				$customer_token_ids[] = $customer_token['id'];
			}

			$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, CreditCardGateway::ID );
			foreach ( $wc_tokens as $token ) {
				if ( ! in_array( $token->get_token(), $customer_token_ids, true ) ) {
					$token->delete();
				}
			}

			$wc_tokens  = WC_Payment_Tokens::get_customer_tokens( $user_id, CreditCardGateway::ID );
			$last_token = end( $wc_tokens );
			if ( $last_token ) {
				$payment_source = $this->card_payment_source( $last_token->get_token(), $wc_order );
			}
		}

		if ( $payment_source ) {
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

			if ( $wc_order->get_payment_method() === CreditCardGateway::ID ) {
				$card_payment_source = $order->payment_source();
				if ( $card_payment_source ) {
					$wc_tokens   = WC_Payment_Tokens::get_customer_tokens( $user_id, CreditCardGateway::ID );
					$last_token  = end( $wc_tokens );
					$expiry      = $card_payment_source->properties()->expiry ?? '';
					$last_digits = $card_payment_source->properties()->last_digits ?? '';

					if ( $last_token && $expiry && $last_digits ) {
						$this->real_time_account_updater_helper->update_wc_card_token( $expiry, $last_digits, $last_token );
					}
				}
			}

			$this->logger->info(
				sprintf(
					'Renewal for order %d is completed.',
					$wc_order->get_id()
				)
			);

			return;
		}

		// Vault v2.
		$token = $this->get_token_for_customer( $customer, $wc_order );
		if ( $token ) {
			if ( $wc_order->get_payment_method() === CreditCardGateway::ID ) {
				$payment_source = $this->card_payment_source( $token->id(), $wc_order );

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

			if ( $wc_order->get_payment_method() === PayPalGateway::ID ) {
				$order = $this->order_endpoint->create(
					array( $purchase_unit ),
					$shipping_preference,
					$payer,
					$token
				);

				$this->handle_paypal_order( $wc_order, $order );

				$this->logger->info(
					sprintf(
						'Renewal for order %d is completed.',
						$wc_order->get_id()
					)
				);
			}
		}
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
			return false;
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

			$payment_source = $order->payment_source();
			if ( $payment_source instanceof PaymentSource ) {
				$this->update_payment_source( $payment_source, $wc_order );
			}
		}

		$this->handle_new_order_status( $order, $wc_order );

		if ( $this->capture_authorized_downloads( $order ) ) {
			$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
		}
	}

	/**
	 * Returns a Card payment source.
	 *
	 * @param string   $token Vault token id.
	 * @param WC_Order $wc_order WC order.
	 * @return PaymentSource
	 * @throws NotFoundException If setting is not found.
	 */
	private function card_payment_source( string $token, WC_Order $wc_order ): PaymentSource {
		$properties = array(
			'vault_id' => $token,
		);

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $wc_order );
		$subscription  = end( $subscriptions );
		if ( $subscription ) {
			$transaction = $this->subscription_helper->previous_transaction( $subscription );
			if ( $transaction ) {
				$properties['stored_credential'] = array(
					'payment_initiator'              => 'MERCHANT',
					'payment_type'                   => 'RECURRING',
					'usage'                          => 'SUBSEQUENT',
					'previous_transaction_reference' => $transaction,
				);
			}
		}

		return new PaymentSource(
			'card',
			(object) $properties
		);
	}

	/**
	 * Updates the payment source name to the one really used for the payment.
	 *
	 * @param PaymentSource $payment_source The Payment Source.
	 * @param \WC_Order     $wc_order WC order.
	 * @return void
	 */
	private function update_payment_source( PaymentSource $payment_source, \WC_Order $wc_order ): void {
		if ( ! $payment_source->name() ) {
			return;
		}
		try {
			$wc_order->set_payment_method_title( $this->funding_source_renderer->render_name( $payment_source->name() ) );
			$wc_order->save();
		} catch ( \Exception $e ) {
			$this->logger->error(
				sprintf(
					'Failed to update payment source to "%1$s" on order %2$d',
					$payment_source->name(),
					$wc_order->get_id()
				)
			);
		}
	}
}
