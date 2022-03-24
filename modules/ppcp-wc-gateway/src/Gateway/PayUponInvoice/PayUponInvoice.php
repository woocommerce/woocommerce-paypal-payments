<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;

class PayUponInvoice {

	/**
	 * @var string
	 */
	protected $module_url;

	/**
	 * @var FraudNet
	 */
	protected $fraud_net;

	/**
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	public function __construct(
		string $module_url,
		FraudNet $fraud_net,
		OrderEndpoint $order_endpoint,
		LoggerInterface $logger
	) {
		$this->module_url = $module_url;
		$this->fraud_net  = $fraud_net;
		$this->order_endpoint = $order_endpoint;
		$this->logger = $logger;
	}

	public function init() {
		add_filter('ppcp_partner_referrals_data', function ($data) {
			$data['products'][] = 'PAYMENT_METHODS';
			$data['capabilities'][] = 'PAY_UPON_INVOICE';

			return $data;
		});

		add_action(
			'wp_footer',
			array( $this, 'add_parameter_block' )
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'register_assets' )
		);

		add_filter( 'woocommerce_billing_fields', function($billing_fields) {
			$billing_fields['billing_birth_date'] = array(
				'type'        => 'date',
				'label'       => __('Birth date', 'woocommerce-paypal-payments'),
				'class'       => array('form-row-wide'),
				'required'    => true,
				'clear'       => true,
			);

			return $billing_fields;
		});

		add_filter( 'woocommerce_email_recipient_customer_on_hold_order', function( $recipient, $order, $email) {
			if($order->get_payment_method() === PayUponInvoiceGateway::ID) {
				return '';
			}

			return $recipient;
		}, 10, 3 );

		add_action('ppcp_payment_capture_completed_webhook_handler', function (WC_Order $wc_order, string $order_id) {
			try {
				$payment_instructions = $this->order_endpoint->order_payment_instructions($order_id);
				$wc_order->update_meta_data( 'ppcp_ratepay_payment_instructions_payment_reference', $payment_instructions );
				$this->logger->info("Ratepay payment instructions added to order #{$wc_order->get_id()}.");
			} catch (RuntimeException $exception) {
				$this->logger->error($exception->getMessage());
			}
		}, 10, 2);

		add_filter( 'woocommerce_gateway_description', function($description, $id) {
			if(PayUponInvoiceGateway::ID === $id) {
				$description .= __( '<span style="float:left;margin-top:20px;margin-bottom:20px;">By clicking on the button, you agree to the <a href="https://www.ratepay.com/legal-payment-terms">terms of payment</a> and <a href="https://www.ratepay.com/legal-payment-dataprivacy">performance of a risk check</a> from the payment partner, Ratepay. You also agree to PayPal’s <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=eng_DE&_ga=1.267010504.718583817.1563460395">privacy statement</a>. If your request to purchase upon invoice is accepted, the purchase price claim will be assigned to Ratepay, and you may only pay Ratepay, not the merchant.</span>', 'woocommerce-paypal-payments');
			}
			return $description;
		}, 10, 2);
	}

	public function add_parameter_block() { ?>
		<script type="application/json" fncls="fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99">{"f":"<?php echo esc_attr($this->fraud_net->sessionId()); ?>","s":"<?php echo esc_attr($this->fraud_net->sourceWebsiteId()); ?>"}</script>
		<script type="text/javascript" src="https://c.paypal.com/da/r/fb.js"></script>
		<?php
	}

	public function register_assets() {
		wp_enqueue_script(
			'ppcp-pay-upon-invoice',
			trailingslashit( $this->module_url ) . 'assets/js/pay-upon-invoice.js',
			array(),
			1
		);
	}
}
