<?php
/**
 * The Bancontact payment gateway.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;

/**
 * Class BancontactGateway
 */
class BancontactGateway extends WC_Payment_Gateway {

	const ID = 'ppcp-bancontact';

	/**
	 * PayPal Orders endpoint.
	 *
	 * @var Orders
	 */
	private $orders_endpoint;

	/**
	 * Purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * BancontactGateway constructor.
	 *
	 * @param Orders              $orders_endpoint PayPal Orders endpoint.
	 * @param PurchaseUnitFactory $purchase_unit_factory Purchase unit factory.
	 */
	public function __construct(
		Orders $orders_endpoint,
		PurchaseUnitFactory $purchase_unit_factory
	) {
		$this->id = self::ID;

		$this->method_title       = __( 'Bancontact', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Bancontact', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', __( 'Bancontact', 'woocommerce-paypal-payments' ) );
		$this->description = $this->get_option( 'description', '' );

		$this->icon = esc_url( 'https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_bancontact_color.svg' );

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		$this->orders_endpoint       = $orders_endpoint;
		$this->purchase_unit_factory = $purchase_unit_factory;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Bancontact', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable Bancontact payment gateway.', 'woocommerce-paypal-payments' ),
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'        => 'text',
				'default'     => $this->title,
				'desc_tip'    => true,
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-paypal-payments' ),
				'type'        => 'text',
				'default'     => $this->description,
				'desc_tip'    => true,
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments' ),
			),
		);
	}

	/**
	 * Processes the order.
	 *
	 * @param int $order_id The WC order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		$wc_order->update_status( 'on-hold', __( 'Awaiting Bancontact to confirm the payment.', 'woocommerce-paypal-payments' ) );

		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$amount        = $purchase_unit->amount()->to_array();

		$request_body = array(
			'intent'                 => 'CAPTURE',
			'payment_source'         => array(
				'bancontact' => array(
					'country_code' => 'BE',
					'name'         => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
				),
			),
			'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
			'purchase_units'         => array(
				array(
					'reference_id' => $purchase_unit->reference_id(),
					'amount'       => array(
						'currency_code' => $amount['currency_code'],
						'value'         => $amount['value'],
					),
					'custom_id'    => $purchase_unit->custom_id(),
					'invoice_id'   => $purchase_unit->invoice_id(),
				),
			),
			'application_context'    => array(
				'locale'     => 'en-BE',
				'return_url' => $this->get_return_url( $wc_order ),
				'cancel_url' => add_query_arg( 'cancelled', 'true', $this->get_return_url( $wc_order ) ),
			),
		);

		try {
			$response = $this->orders_endpoint->create( $request_body );
		} catch ( RuntimeException $exception ) {
			$wc_order->update_status(
				'failed',
				$exception->getMessage()
			);

			return array(
				'result'   => 'failure',
				'redirect' => wc_get_checkout_url(),
			);
		}

		$body = json_decode( $response['body'] );

		$payer_action = '';
		foreach ( $body->links as $link ) {
			if ( $link->rel === 'payer-action' ) {
				$payer_action = $link->href;
			}
		}

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => esc_url( $payer_action ),
		);
	}
}
