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
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;

class PayUponInvoiceGateway extends WC_Payment_Gateway {

	use OrderMetaTrait;

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
	 * @var Environment
	 */
	protected $environment;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		PaymentSourceFactory $payment_source_factory,
		Environment $environment,
		LoggerInterface $logger
	) {
		 $this->id = self::ID;

		$this->method_title       = __( 'Pay Upon Invoice', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.', 'woocommerce-paypal-payments' );

		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		$this->title = $gateway_settings['title'] ?? $this->method_title;
		$this->description = $gateway_settings['description'] ?? __( 'Once you place an order, pay within 30 days. Our payment partner Ratepay will send you payment instructions.', 'woocommerce-paypal-payments' );

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
		$this->environment = $environment;
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
				'desc_tip'     => true,
				'description'  => __('Enable/Disable Pay Upon Invoice payment gateway.', 'woocommerce-paypal-payments'),
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-paypal-payments' ),
				'type' => 'text',
				'default' => $this->title,
				'desc_tip'     => true,
				'description'  => __('This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments'),
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-paypal-payments' ),
				'type' => 'text',
				'default' => $this->description,
				'desc_tip'     => true,
				'description'  => __('This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments'),
			),
			'experience_context'           => array(
				'title'       => __( 'Experience Context', 'woocommerce' ),
				'type'        => 'title',
				'description' => __("Specify brand name, logo and customer service instructions to be presented on Ratepay's payment instruction email sent to the buyer.", 'woocommerce-paypal-payments'),
			),
			'brand_name' => array(
				'title' => __( 'Brand name', 'woocommerce-paypal-payments' ),
				'type' => 'text',
				'default' => '',
				'desc_tip'     => true,
				'description'  => __('Merchant name displayed in the email.', 'woocommerce-paypal-payments'),
			),
		);
	}

	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		$wc_order->update_status( 'on-hold', __( 'Awaiting Pay Upon Invoice payment.', 'woocommerce-paypal-payments' ) );

		$purchase_unit  = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payment_source = $this->payment_source_factory->from_wc_order( $wc_order );

		try {
			$fraudnet_session_id = filter_input(INPUT_POST, 'fraudnet-session-id', FILTER_SANITIZE_STRING) ?? '';

			$order = $this->order_endpoint->create( array( $purchase_unit ), $payment_source, $fraudnet_session_id );
			$this->add_paypal_meta( $wc_order, $order, $this->environment );

			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $wc_order ),
			);
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();

			if(is_array($exception->details())) {
				$details = '';
				foreach ($exception->details() as $detail) {
					$issue = $detail->issue ?? '';
					$field = $detail->field ?? '';
					$description = $detail->description ?? '';
					$details .= $issue . ' ' . $field . ' ' . $description . '<br>';
				}

				$error = $details;
			}

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
