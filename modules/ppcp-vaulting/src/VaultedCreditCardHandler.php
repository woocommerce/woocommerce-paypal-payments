<?php
/**
 * Handles payment through saved credit card.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Psr\Container\ContainerInterface;
use WC_Customer;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Subscription\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;

class VaultedCreditCardHandler
{
	use OrderMetaTrait, TransactionIdHandlingTrait, PaymentsStatusHandlingTrait, FreeTrialHandlerTrait;

	/**
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $payment_token_repository;

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
	 * The shipping_preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	private $shipping_preference_factory;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * The processor for authorized payments.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	protected $authorized_payments_processor;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $config;

	public function __construct(
		SubscriptionHelper $subscription_helper,
		PaymentTokenRepository $payment_token_repository,
		PurchaseUnitFactory $purchase_unit_factory,
		PayerFactory $payer_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		OrderEndpoint $order_endpoint,
		Environment $environment,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		ContainerInterface $config
	)
	{
		$this->subscription_helper = $subscription_helper;
		$this->payment_token_repository = $payment_token_repository;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->payer_factory = $payer_factory;
		$this->shipping_preference_factory = $shipping_preference_factory;
		$this->order_endpoint = $order_endpoint;
		$this->environment = $environment;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->config = $config;
	}

	public function handle_payment(
		string $saved_credit_card,
		WC_Order $wc_order,
		WC_Customer $customer
	): WC_Order {

		$change_payment    = filter_input( INPUT_POST, 'woocommerce_change_payment', FILTER_SANITIZE_STRING );
		if($change_payment) {
			if ( $this->subscription_helper->has_subscription( $wc_order->get_id() ) && $this->subscription_helper->is_subscription_change_payment() ) {
				if ( $saved_credit_card ) {
					update_post_meta( $wc_order->get_id(), 'payment_token_id', $saved_credit_card );

					return $wc_order;
				}
			}
		}

		$tokens   = $this->payment_token_repository->all_for_user_id( $wc_order->get_customer_id() );
		$selected_token = null;
		foreach ( $tokens as $token ) {
			if ( $token->id() === $saved_credit_card ) {
				$selected_token = $token;
				break;
			}
		}
		if ( ! $selected_token ) {
			throw new RuntimeException('Saved card token not found.');
		}

		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payer         = $this->payer_factory->from_customer( $customer );

		$shipping_preference = $this->shipping_preference_factory->from_state(
			$purchase_unit,
			''
		);

		try {
			$order = $this->order_endpoint->create(
				array( $purchase_unit ),
				$shipping_preference,
				$payer,
				$selected_token
			);

			$this->add_paypal_meta( $wc_order, $order, $this->environment );

			if ( ! $order->status()->is( OrderStatus::COMPLETED ) ) {
				throw new RuntimeException("Unexpected status for order {$order->id()} using a saved card: {$order->status()->name()}.");
			}

			if ( ! in_array(
				$order->intent(),
				array( 'CAPTURE', 'AUTHORIZE' ),
				true
			) ) {
				throw new RuntimeException("Could neither capture nor authorize order {$order->id()} using a saved card. Status: {$order->status()->name()}. Intent: {$order->intent()}.");
			}

			if ( $order->intent() === 'AUTHORIZE' ) {
				$order = $this->order_endpoint->authorize( $order );
				$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );
			}

			$transaction_id = $this->get_paypal_order_transaction_id( $order );
			if ( $transaction_id ) {
				$this->update_transaction_id( $transaction_id, $wc_order );
			}

			$this->handle_new_order_status( $order, $wc_order );

			if ( $this->is_free_trial_order( $wc_order ) ) {
				$this->authorized_payments_processor->void_authorizations( $order );
				$wc_order->payment_complete();
			} elseif ( $this->config->has( 'intent' ) && strtoupper( (string) $this->config->get( 'intent' ) ) === 'CAPTURE' ) {
				$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
			}

			return $wc_order;
		} catch ( RuntimeException $error ) {
			throw new RuntimeException($error->getMessage());
		}
	}
}
