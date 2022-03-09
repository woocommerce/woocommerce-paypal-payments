<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

class PayUponInvoice {

	/**
	 * @var string
	 */
	protected $module_url;

	/**
	 * @var FraudNet
	 */
	protected $fraud_net;

	public function __construct( string $module_url, FraudNet $fraud_net ) {
		$this->module_url = $module_url;
		$this->fraud_net  = $fraud_net;
	}

	public function init() {
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
	}

	public function add_parameter_block() { ?>
		<script type="application/json" fncls="fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99">{"f":"<?php echo $this->fraud_net->sessionId(); ?>","s":"<?php echo $this->fraud_net->sourceWebsiteId(); ?>"}</script>
		<script type="text/javascript" src="https://c.paypal.com/da/r/fb.js"></script>
		<?php
	}

	public function add_legal_text() {
		$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
		?>
		<p id="ppcp-pui-legal-text"
		   style="display:none;"><?php echo wp_kses_post( $gateway_settings['legal_text'] ?? '' ); ?></p>
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
