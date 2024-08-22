<?php
/**
 * Processes orders for the gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderHelper;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Exception\PayPalOrderMissingException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class OrderProcessor
 */
class OrderProcessor {

	use OrderMetaTrait, PaymentsStatusHandlingTrait, TransactionIdHandlingTrait;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	protected $payment_token_repository;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The Order Endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The Order Factory.
	 *
	 * @var OrderFactory
	 */
	private $order_factory;

	/**
	 * The helper for 3d secure.
	 *
	 * @var ThreeDSecure
	 */
	private $threed_secure;

	/**
	 * The processor for authorized payments.
	 *
	 * @var AuthorizedPaymentsProcessor
	 */
	private $authorized_payments_processor;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * A logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	private $subscription_helper;

	/**
	 * The order helper.
	 *
	 * @var OrderHelper
	 */
	private $order_helper;

	/**
	 * The PurchaseUnit factory.
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
	 * Array to store temporary order data changes to restore after processing.
	 *
	 * @var array
	 */
	private $restore_order_data = array();

	/**
	 * OrderProcessor constructor.
	 *
	 * @param SessionHandler              $session_handler The Session Handler.
	 * @param OrderEndpoint               $order_endpoint The Order Endpoint.
	 * @param OrderFactory                $order_factory The Order Factory.
	 * @param ThreeDSecure                $three_d_secure The ThreeDSecure Helper.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments Processor.
	 * @param Settings                    $settings The Settings.
	 * @param LoggerInterface             $logger A logger service.
	 * @param Environment                 $environment The environment.
	 * @param SubscriptionHelper          $subscription_helper The subscription helper.
	 * @param OrderHelper                 $order_helper The order helper.
	 * @param PurchaseUnitFactory         $purchase_unit_factory The PurchaseUnit factory.
	 * @param PayerFactory                $payer_factory The payer factory.
	 * @param ShippingPreferenceFactory   $shipping_preference_factory The shipping_preference factory.
	 */
	public function __construct(
		SessionHandler $session_handler,
		OrderEndpoint $order_endpoint,
		OrderFactory $order_factory,
		ThreeDSecure $three_d_secure,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		Settings $settings,
		LoggerInterface $logger,
		Environment $environment,
		SubscriptionHelper $subscription_helper,
		OrderHelper $order_helper,
		PurchaseUnitFactory $purchase_unit_factory,
		PayerFactory $payer_factory,
		ShippingPreferenceFactory $shipping_preference_factory
	) {

		$this->session_handler               = $session_handler;
		$this->order_endpoint                = $order_endpoint;
		$this->order_factory                 = $order_factory;
		$this->threed_secure                 = $three_d_secure;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->settings                      = $settings;
		$this->environment                   = $environment;
		$this->logger                        = $logger;
		$this->subscription_helper           = $subscription_helper;
		$this->order_helper                  = $order_helper;
		$this->purchase_unit_factory         = $purchase_unit_factory;
		$this->payer_factory                 = $payer_factory;
		$this->shipping_preference_factory   = $shipping_preference_factory;
	}

	/**
	 * Processes a given WooCommerce order and captured/authorizes the connected PayPal orders.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 *
	 * @throws PayPalOrderMissingException If no PayPal order.
	 * @throws Exception If processing fails.
	 */
	public function process( WC_Order $wc_order ): void {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY ) ?: wc_clean( wp_unslash( $_POST['paypal_order_id'] ?? '' ) );
			if ( is_string( $order_id ) && $order_id ) {
				try {
					$order = $this->order_endpoint->order( $order_id );
				} catch ( RuntimeException $exception ) {
					throw new Exception( __( 'Could not retrieve PayPal order.', 'woocommerce-paypal-payments' ) );
				}
			} else {
				$this->logger->warning(
					sprintf(
						'No PayPal order ID found in order #%d meta.',
						$wc_order->get_id()
					)
				);

				throw new PayPalOrderMissingException(
					esc_attr__(
						'There was an error processing your order. Please check for any charges in your payment method and review your order history before placing the order again.',
						'woocommerce-paypal-payments'
					)
				);
			}
		}

		$this->add_paypal_meta( $wc_order, $order, $this->environment );

		if ( $this->order_helper->contains_physical_goods( $order ) && ! $this->order_is_ready_for_process( $order ) ) {
			throw new Exception(
				__(
					'The payment is not ready for processing yet.',
					'woocommerce-paypal-payments'
				)
			);
		}

		$order = $this->patch_order( $wc_order, $order );

		if ( $order->intent() === 'CAPTURE' ) {
			$order = $this->order_endpoint->capture( $order );
		}

		if ( $order->intent() === 'AUTHORIZE' ) {
			$order = $this->order_endpoint->authorize( $order );

			$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );

			if ( $this->subscription_helper->has_subscription( $wc_order->get_id() ) ) {
				$wc_order->update_meta_data( '_ppcp_captured_vault_webhook', 'false' );
			}
		}

		$transaction_id = $this->get_paypal_order_transaction_id( $order );

		if ( $transaction_id ) {
			$this->update_transaction_id( $transaction_id, $wc_order );
		}

		$this->handle_new_order_status( $order, $wc_order );

		if ( $this->capture_authorized_downloads( $order ) ) {
			$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
		}

		do_action( 'woocommerce_paypal_payments_after_order_processor', $wc_order, $order );
	}

	/**
	 * Processes a given WooCommerce order and captured/authorizes the connected PayPal orders.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Order    $order The PayPal order.
	 *
	 * @throws Exception If processing fails.
	 */
	public function process_captured_and_authorized( WC_Order $wc_order, Order $order ): void {
		$this->add_paypal_meta( $wc_order, $order, $this->environment );

		if ( $order->intent() === 'AUTHORIZE' ) {
			$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false' );

			if ( $this->subscription_helper->has_subscription( $wc_order->get_id() ) ) {
				$wc_order->update_meta_data( '_ppcp_captured_vault_webhook', 'false' );
			}
		}

		$transaction_id = $this->get_paypal_order_transaction_id( $order );

		if ( $transaction_id ) {
			$this->update_transaction_id( $transaction_id, $wc_order );
		}

		$this->handle_new_order_status( $order, $wc_order );

		if ( $this->capture_authorized_downloads( $order ) ) {
			$this->authorized_payments_processor->capture_authorized_payment( $wc_order );
		}

		do_action( 'woocommerce_paypal_payments_after_order_processor', $wc_order, $order );
	}

	/**
	 * Creates a PayPal order for the given WC order.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @return Order
	 * @throws RuntimeException If order creation fails.
	 */
	public function create_order( WC_Order $wc_order ): Order {
		$pu                  = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$shipping_preference = $this->shipping_preference_factory->from_state( $pu, 'checkout' );
		$order               = $this->order_endpoint->create(
			array( $pu ),
			$shipping_preference,
			$this->payer_factory->from_wc_order( $wc_order ),
			null,
			'',
			ApplicationContext::USER_ACTION_PAY_NOW
		);

		return $order;
	}

	/**
	 * Returns if an order should be captured immediately.
	 *
	 * @param Order $order The PayPal order.
	 *
	 * @return bool
	 */
	private function capture_authorized_downloads( Order $order ): bool {
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
	 * Patches a given PayPal order with a WooCommerce order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Order    $order The PayPal order.
	 *
	 * @return Order
	 */
	public function patch_order( WC_Order $wc_order, Order $order ): Order {
		$this->apply_outbound_order_filters( $wc_order );
		$updated_order = $this->order_factory->from_wc_order( $wc_order, $order );
		$this->restore_order_from_filters( $wc_order );

		$order = $this->order_endpoint->patch_order_with( $order, $updated_order );

		return $order;
	}

	/**
	 * Whether a given order is ready for processing.
	 *
	 * @param Order $order The order.
	 *
	 * @return bool
	 */
	private function order_is_ready_for_process( Order $order ): bool {
		if ( $order->status()->is( OrderStatus::APPROVED ) || $order->status()->is( OrderStatus::CREATED ) ) {
			return true;
		}

		$payment_source = $order->payment_source();
		if ( ! $payment_source ) {
			return false;
		}

		if ( $payment_source->name() !== 'card' ) {
			return false;
		}

		return in_array(
			$this->threed_secure->proceed_with_order( $order ),
			array(
				ThreeDSecure::NO_DECISION,
				ThreeDSecure::PROCCEED,
			),
			true
		);
	}

	/**
	 * Applies filters to the WC_Order, so they are reflected only on PayPal Order.
	 *
	 * @param WC_Order $wc_order The WoocOmmerce Order.
	 * @return void
	 */
	private function apply_outbound_order_filters( WC_Order $wc_order ): void {
		$items = $wc_order->get_items();

		$this->restore_order_data['names'] = array();

		foreach ( $items as $item ) {
			if ( ! $item instanceof \WC_Order_Item ) {
				continue;
			}

			$original_name = $item->get_name();
			$new_name      = apply_filters( 'woocommerce_paypal_payments_order_line_item_name', $original_name, $item->get_id(), $wc_order->get_id() );

			if ( $new_name !== $original_name ) {
				$this->restore_order_data['names'][ $item->get_id() ] = $original_name;
				$item->set_name( $new_name );
			}
		}
	}

	/**
	 * Restores the WC_Order to it's state before filters.
	 *
	 * @param WC_Order $wc_order The WooCommerce Order.
	 * @return void
	 */
	private function restore_order_from_filters( WC_Order $wc_order ): void {
		if ( is_array( $this->restore_order_data['names'] ?? null ) ) {
			foreach ( $this->restore_order_data['names'] as $wc_item_id => $original_name ) {
				$wc_item = $wc_order->get_item( $wc_item_id, false );

				if ( $wc_item ) {
					$wc_item->set_name( $original_name );
				}
			}
		}
	}
}
