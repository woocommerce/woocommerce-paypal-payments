<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;

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
			'woocommerce_review_order_after_submit',
			array( $this, 'add_legal_text' )
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

		add_action('ppcp_payment_capture_completed_webhook_handler', function (string $order_id) {
			$this->order_endpoint->order_payment_instructions($order_id);
		});
	}

	public function add_parameter_block() { ?>
		<script type="application/json" fncls="fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99">{"f":"<?php echo esc_attr($this->fraud_net->sessionId()); ?>","s":"<?php echo esc_attr($this->fraud_net->sourceWebsiteId()); ?>"}</script>
		<script type="text/javascript" src="https://c.paypal.com/da/r/fb.js"></script>
		<?php
	}

	public function add_legal_text() {
		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		?>
		<p id="ppcp-pui-legal-text"
		   style="display:none;"><?php echo wp_kses_post( $gateway_settings['button_legal_text_en'] ?? '' ); ?></p>
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
