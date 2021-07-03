<?php
/**
 * The Order factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;

/**
 * Class OrderFactory
 */
class OrderFactory {

	/**
	 * The PurchaseUnit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The Payer factory.
	 *
	 * @var PayerFactory
	 */
	private $payer_factory;

	/**
	 * The ApplicationContext repository.
	 *
	 * @var ApplicationContextRepository
	 */
	private $application_context_repository;

	/**
	 * The ApplicationContext factory.
	 *
	 * @var ApplicationContextFactory
	 */
	private $application_context_factory;

	/**
	 * The PaymentSource factory.
	 *
	 * @var PaymentSourceFactory
	 */
	private $payment_source_factory;

	/**
	 * OrderFactory constructor.
	 *
	 * @param PurchaseUnitFactory          $purchase_unit_factory The PurchaseUnit factory.
	 * @param PayerFactory                 $payer_factory The Payer factory.
	 * @param ApplicationContextRepository $application_context_repository The Application Context repository.
	 * @param ApplicationContextFactory    $application_context_factory The Application Context factory.
	 * @param PaymentSourceFactory         $payment_source_factory The Payment Source factory.
	 */
	public function __construct(
		PurchaseUnitFactory $purchase_unit_factory,
		PayerFactory $payer_factory,
		ApplicationContextRepository $application_context_repository,
		ApplicationContextFactory $application_context_factory,
		PaymentSourceFactory $payment_source_factory
	) {

		$this->purchase_unit_factory          = $purchase_unit_factory;
		$this->payer_factory                  = $payer_factory;
		$this->application_context_repository = $application_context_repository;
		$this->application_context_factory    = $application_context_factory;
		$this->payment_source_factory         = $payment_source_factory;
	}

	/**
	 * Creates an Order object based off a WooCommerce order and another Order object.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 * @param Order     $order The order object.
	 *
	 * @return Order
	 */
	public function from_wc_order( \WC_Order $wc_order, Order $order ): Order {
		$purchase_units = array( $this->purchase_unit_factory->from_wc_order( $wc_order ) );

		return new Order(
			$order->id(),
			$purchase_units,
			$order->status(),
			$order->application_context(),
			$order->payment_source(),
			$order->payer(),
			$order->intent(),
			$order->create_time(),
			$order->update_time()
		);
	}

	/**
	 * Returns an Order object based off a PayPal Response.
	 *
	 * @param \stdClass $order_data The JSON object.
	 *
	 * @return Order
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $order_data ): Order {
		if ( ! isset( $order_data->id ) ) {
			throw new RuntimeException(
				__( 'Order does not contain an id.', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $order_data->purchase_units ) || ! is_array( $order_data->purchase_units ) ) {
			throw new RuntimeException(
				__( 'Order does not contain items.', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $order_data->status ) ) {
			throw new RuntimeException(
				__( 'Order does not contain status.', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $order_data->intent ) ) {
			throw new RuntimeException(
				__( 'Order does not contain intent.', 'woocommerce-paypal-payments' )
			);
		}

		$purchase_units = array_map(
			function ( \stdClass $data ): PurchaseUnit {
				return $this->purchase_unit_factory->from_paypal_response( $data );
			},
			$order_data->purchase_units
		);

		$create_time         = ( isset( $order_data->create_time ) ) ?
			\DateTime::createFromFormat( 'Y-m-d\TH:i:sO', $order_data->create_time )
			: null;
		$update_time         = ( isset( $order_data->update_time ) ) ?
			\DateTime::createFromFormat( 'Y-m-d\TH:i:sO', $order_data->update_time )
			: null;
		$payer               = ( isset( $order_data->payer ) ) ?
			$this->payer_factory->from_paypal_response( $order_data->payer )
			: null;
		$application_context = ( isset( $order_data->application_context ) ) ?
			$this->application_context_factory->from_paypal_response( $order_data->application_context )
			: null;
		$payment_source      = ( isset( $order_data->payment_source ) ) ?
			$this->payment_source_factory->from_paypal_response( $order_data->payment_source ) :
			null;

		return new Order(
			$order_data->id,
			$purchase_units,
			new OrderStatus( $order_data->status ),
			$application_context,
			$payment_source,
			$payer,
			$order_data->intent,
			$create_time,
			$update_time
		);
	}
}
