<?php
/**
 * Processes orders for the gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
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
	 * The last error.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * A logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

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
	 */
	public function __construct(
		SessionHandler $session_handler,
		OrderEndpoint $order_endpoint,
		OrderFactory $order_factory,
		ThreeDSecure $three_d_secure,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		Settings $settings,
		LoggerInterface $logger,
		Environment $environment
	) {

		$this->session_handler               = $session_handler;
		$this->order_endpoint                = $order_endpoint;
		$this->order_factory                 = $order_factory;
		$this->threed_secure                 = $three_d_secure;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->settings                      = $settings;
		$this->environment                   = $environment;
		$this->logger                        = $logger;
	}

	/**
	 * Processes a given WooCommerce order and captured/authorizes the connected PayPal orders.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @return bool
	 */
	public function process( \WC_Order $wc_order ): bool {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}

		$this->add_paypal_meta( $wc_order, $order, $this->environment );

		$error_message = null;
		if ( ! $this->order_is_approved( $order ) ) {
			$error_message = __(
				'The payment has not been approved yet.',
				'woocommerce-paypal-payments'
			);
		}
		if ( $error_message ) {
			$this->last_error = sprintf(
				// translators: %s is the message of the error.
				__( 'Payment error: %s', 'woocommerce-paypal-payments' ),
				$error_message
			);
			return false;
		}

		$order = $this->patch_order( $wc_order, $order );

		if ( $order->intent() === 'CAPTURE' ) {
			$order = $this->order_endpoint->capture( $order );
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

		if ( $this->capture_authorized_downloads( $order ) && AuthorizedPaymentsProcessor::SUCCESSFUL === $this->authorized_payments_processor->process( $wc_order ) ) {
			$wc_order->add_order_note(
				__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
			);
			$wc_order->update_meta_data( AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'true' );
			$wc_order->update_status( 'processing' );
		}
		$this->last_error = '';
		return true;
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
	 * Returns the last error.
	 *
	 * @return string
	 */
	public function last_error(): string {

		return $this->last_error;
	}

	/**
	 * Patches a given PayPal order with a WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 * @param Order     $order The PayPal order.
	 *
	 * @return Order
	 */
	public function patch_order( \WC_Order $wc_order, Order $order ): Order {
		$updated_order = $this->order_factory->from_wc_order( $wc_order, $order );
		$order         = $this->order_endpoint->patch_order_with( $order, $updated_order );
		return $order;
	}

	/**
	 * Whether a given order is approved.
	 *
	 * @param Order $order The order.
	 *
	 * @return bool
	 */
	private function order_is_approved( Order $order ): bool {

		if ( $order->status()->is( OrderStatus::APPROVED ) ) {
			return true;
		}

		if ( ! $order->payment_source() || ! $order->payment_source()->card() ) {
			return false;
		}

		$is_approved = in_array(
			$this->threed_secure->proceed_with_order( $order ),
			array(
				ThreeDSecure::NO_DECISION,
				ThreeDSecure::PROCCEED,
			),
			true
		);
		return $is_approved;
	}
}
