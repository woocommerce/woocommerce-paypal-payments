<?php
/**
 * Processes orders for the gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CartRepository;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class OrderProcessor
 */
class OrderProcessor {

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The Cart Repository.
	 *
	 * @var CartRepository
	 */
	private $cart_repository;

	/**
	 * The Order Endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The Payments Endpoint.
	 *
	 * @var PaymentsEndpoint
	 */
	private $payments_endpoint;

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
	 * OrderProcessor constructor.
	 *
	 * @param SessionHandler              $session_handler The Session Handler.
	 * @param CartRepository              $cart_repository The Cart Repository.
	 * @param OrderEndpoint               $order_endpoint The Order Endpoint.
	 * @param PaymentsEndpoint            $payments_endpoint The Payments Endpoint.
	 * @param OrderFactory                $order_factory The Order Factory.
	 * @param ThreeDSecure                $three_d_secure The ThreeDSecure Helper.
	 * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments Processor.
	 * @param Settings                    $settings The Settings.
	 */
	public function __construct(
		SessionHandler $session_handler,
		CartRepository $cart_repository,
		OrderEndpoint $order_endpoint,
		PaymentsEndpoint $payments_endpoint,
		OrderFactory $order_factory,
		ThreeDSecure $three_d_secure,
		AuthorizedPaymentsProcessor $authorized_payments_processor,
		Settings $settings
	) {

		$this->session_handler               = $session_handler;
		$this->cart_repository               = $cart_repository;
		$this->order_endpoint                = $order_endpoint;
		$this->payments_endpoint             = $payments_endpoint;
		$this->order_factory                 = $order_factory;
		$this->threed_secure                 = $three_d_secure;
		$this->authorized_payments_processor = $authorized_payments_processor;
		$this->settings                      = $settings;
	}

	/**
	 * Processes a given WooCommerce order and captured/authorizes the connected PayPal orders.
	 *
	 * @param \WC_Order    $wc_order The WooCommerce order.
	 * @param \WooCommerce $woocommerce The WooCommerce object.
	 *
	 * @return bool
	 */
	public function process( \WC_Order $wc_order, \WooCommerce $woocommerce ): bool {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}
		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, $order->id() );
		$wc_order->update_meta_data( PayPalGateway::INTENT_META_KEY, $order->intent() );

		$error_message = null;
		if ( ! $order || ! $this->order_is_approved( $order ) ) {
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
			$wc_order->update_meta_data( PayPalGateway::CAPTURED_META_KEY, 'false' );
		}

		$wc_order->update_status(
			'on-hold',
			__( 'Awaiting payment.', 'woocommerce-paypal-payments' )
		);
		if ( $order->status()->is( OrderStatus::COMPLETED ) && $order->intent() === 'CAPTURE' ) {
			$wc_order->update_status(
				'processing',
				__( 'Payment received.', 'woocommerce-paypal-payments' )
			);
		}

		if ( $this->capture_authorized_downloads( $order ) && $this->authorized_payments_processor->process( $wc_order ) ) {
			$wc_order->add_order_note(
				__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
			);
			$wc_order->update_meta_data( PayPalGateway::CAPTURED_META_KEY, 'true' );
			$wc_order->update_status( 'processing' );
		}
		$woocommerce->cart->empty_cart();
		$this->session_handler->destroy_session_data();
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
