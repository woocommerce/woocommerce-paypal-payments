<?php
/**
 * The Pay upon invoice Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

class PayUponInvoiceGateway extends WC_Payment_Gateway {

	const ID = 'ppcp-pay-upon-invoice-gateway';

	/**
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * @var PurchaseUnitFactory
	 */
	protected $purchase_unit_factory;

	/**
	 * @var PaymentSourceFactory
	 */
	protected $payment_source_factory;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		PaymentSourceFactory $payment_source_factory,
		LoggerInterface $logger
	) {
		 $this->id = self::ID;

		$this->method_title       = __( 'Pay Upon Invoice', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Once you place an order, pay within 30 days. Our payment partner Ratepay will send you payment instructions.', 'woocommerce-paypal-payments' );
		$this->title              = $this->method_title;
		$this->description        = $this->method_description;

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		$this->order_endpoint         = $order_endpoint;
		$this->purchase_unit_factory  = $purchase_unit_factory;
		$this->payment_source_factory = $payment_source_factory;
		$this->logger                 = $logger;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Pay upon Invoice', 'woocommerce-paypal-payments' ),
				'default' => 'no',
			),
			'button_legal_text_en' => array(
				'title'   => __( 'Button legal text (English)', 'woocommerce-paypal-payments' ),
				'type'    => 'textarea',
				'default' => 'By clicking on the button, you agree to the <a href="https://www.ratepay.com/legal-payment-terms">terms of payment</a> and <a href="https://www.ratepay.com/legal-payment-dataprivacy">performance of a risk check</a> from the payment partner, Ratepay. You also agree to PayPal’s <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=eng_DE&_ga=1.267010504.718583817.1563460395">privacy statement</a>. If your request to purchase upon invoice is accepted, the purchase price claim will be assigned to Ratepay, and you may only pay Ratepay, not the merchant.',
			),
			'button_legal_text_de' => array(
				'title'   => __( 'Button legal text (German)', 'woocommerce-paypal-payments' ),
				'type'    => 'textarea',
				'default' => 'Mit Klicken auf den Button akzeptieren Sie die <a href="https://www.ratepay.com/legal-payment-terms">Ratepay Zahlungsbedingungen</a> und erklären sich mit der Durchführung einer <a href="https://www.ratepay.com/legal-payment-dataprivacy">Risikoprüfung durch Ratepay</a>, unseren Partner, einverstanden. Sie akzeptieren auch PayPals <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=de_DE&_ga=1.232450521.718583817.1563460395">Datenschutzerklärung</a>. Falls Ihre Transaktion per Kauf auf Rechnung erfolgreich abgewickelt werden kann, wird der Kaufpreis an Ratepay abgetreten und Sie dürfen nur an Ratepay überweisen, nicht an den Händler.',
			),
		);
	}

	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		$wc_order->update_status( 'on-hold', __( 'Awaiting Pay Upon Invoice payment', 'woocommerce-paypal-payments' ) );

		$purchase_unit  = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payment_source = $this->payment_source_factory->from_wc_order( $wc_order );

		try {
			$this->order_endpoint->create( array( $purchase_unit ), $payment_source );

			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $wc_order ),
			);
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();

			$this->logger->error( $error );
			wc_add_notice( $error, 'error' );

			$wc_order->update_status(
				'failed',
				$error
			);

			return array(
				'result'   => 'failure',
				'redirect' => wc_get_checkout_url(),
			);
		}
	}
}
