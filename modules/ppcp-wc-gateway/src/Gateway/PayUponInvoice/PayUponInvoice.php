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
		$this->module_url     = $module_url;
		$this->fraud_net      = $fraud_net;
		$this->order_endpoint = $order_endpoint;
		$this->logger         = $logger;
	}

	public function init() {
		add_filter(
			'ppcp_partner_referrals_data',
			function ( $data ) {
				if ( in_array( 'PPCP', $data['products'] ) ) {
					$data['products'][]     = 'PAYMENT_METHODS';
					$data['capabilities'][] = 'PAY_UPON_INVOICE';
				}
				return $data;
			}
		);

		add_action(
			'wp_footer',
			array( $this, 'add_parameter_block' )
		);

		add_action(
			'wp_enqueue_scripts',
			array( $this, 'register_assets' )
		);

		add_filter(
			'woocommerce_email_recipient_customer_on_hold_order',
			function( $recipient, $order, $email ) {
				if ( $order->get_payment_method() === PayUponInvoiceGateway::ID ) {
					return '';
				}

				return $recipient;
			},
			10,
			3
		);

		add_action(
			'ppcp_payment_capture_completed_webhook_handler',
			function ( WC_Order $wc_order, string $order_id ) {
				try {
					$payment_instructions = $this->order_endpoint->order_payment_instructions( $order_id );
					$wc_order->update_meta_data( 'ppcp_ratepay_payment_instructions_payment_reference', $payment_instructions );
					$this->logger->info( "Ratepay payment instructions added to order #{$wc_order->get_id()}." );
				} catch ( RuntimeException $exception ) {
					$this->logger->error( $exception->getMessage() );
				}
			},
			10,
			2
		);

		add_action( 'woocommerce_email_before_order_table', function(WC_Order $order, $sent_to_admin) {
			if(! $sent_to_admin && PayUponInvoiceGateway::ID === $order->get_payment_method() && $order->has_status( 'processing' )) {
				$this->logger->info( "Adding Ratepay payment instructions to email for order #{$order->get_id()}." );

				$instructions = $order->get_meta('ppcp_ratepay_payment_instructions_payment_reference');

				$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
				$merchant_name = $gateway_settings['brand_name'] ?? '';

				$order_date = $order->get_date_created();
				$order_purchase_date = $order_date->date('d-m-Y');
				$order_time = $order_date->date('H:i:s');
				$order_date = $order_date->date('d-m-Y H:i:s');
				$order_date_30d = date( 'd-m-Y', strtotime( $order_date . ' +30 days' ));

				$payment_reference = $instructions[0] ?? '';
				$bic = $instructions[1]->bic ?? '';
				$bank_name = $instructions[1]->bank_name ?? '';
				$iban = $instructions[1]->iban ?? '';
				$account_holder_name = $instructions[1]->account_holder_name ?? '';

				echo "<p>Für Ihre Bestellung #{$order->get_id()} ({$order_purchase_date} $order_time) bei {$merchant_name} haben Sie die Zahlung mittels “Rechnungskauf mit Ratepay“ gewählt.";
				echo "<br>Bitte benutzen Sie die folgenden Informationen für Ihre Überweisung:</br>";
				echo "<p>Bitte überweisen Sie den Betrag in Höhe von {$order->get_total()} bis zum {$order_date_30d} auf das unten angegebene Konto. Wichtig: Bitte geben Sie unbedingt als Verwendungszweck {$payment_reference} an, sonst kann die Zahlung nicht zugeordnet werden.</p>";
				echo "<ul>";
				echo "<li>Empfänger: {$account_holder_name}</li>";
				echo "<li>IBAN: {$iban}</li>";
				echo "<li>BIC: {$bic}</li>";
				echo "<li>Name der Bank: {$bank_name}</li>";
				echo "<li>Verwendungszweck: {$payment_reference}</li>";
				echo "</ul>";

				echo "<p>{$merchant_name} hat die Forderung gegen Sie an die PayPal (Europe) S.à r.l. et Cie, S.C.A. abgetreten, die wiederum die Forderung an Ratepay GmbH abgetreten hat. Zahlungen mit schuldbefreiender Wirkung können nur an die Ratepay GmbH geleistet werden.</p>";

				echo "<p>Mit freundlichen Grüßen";
				echo "<br>";
				echo "{$merchant_name}</p>";
			}
		}, 10, 3 );

		add_filter(
			'woocommerce_gateway_description',
			function( $description, $id ) {
				if ( PayUponInvoiceGateway::ID === $id ) {
					ob_start();
					echo '<div style="padding: 20px 0;">';

					woocommerce_form_field(
						'billing_birth_date',
						array(
							'type'     => 'date',
							'label'    => __( 'Birth date', 'woocommerce-paypal-payments' ),
							'class'    => array( 'form-row-wide' ),
							'required' => true,
							'clear'    => true,
						)
					);

					echo '</div><div>';
					_e( 'By clicking on the button, you agree to the <a href="https://www.ratepay.com/legal-payment-terms">terms of payment</a> and <a href="https://www.ratepay.com/legal-payment-dataprivacy">performance of a risk check</a> from the payment partner, Ratepay. You also agree to PayPal’s <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=eng_DE&_ga=1.267010504.718583817.1563460395">privacy statement</a>. If your request to purchase upon invoice is accepted, the purchase price claim will be assigned to Ratepay, and you may only pay Ratepay, not the merchant.', 'woocommerce-paypal-payments' );
					echo '</div>';

					$description .= ob_get_clean();
				}

				return $description;
			},
			10,
			2
		);

		add_action(
			'woocommerce_after_checkout_validation',
			function( $fields, $errors ) {
				if ( $fields['billing_country'] !== 'DE' ) {
					$errors->add( 'validation', __( 'Billing country not available.', 'woocommerce-paypal-payments' ) );
				}
			},
			10,
			2
		);
	}

	public function add_parameter_block() { ?>
		<script type="application/json" fncls="fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99">{"sandbox":true,"f":"<?php echo esc_attr( $this->fraud_net->sessionId() ); ?>","s":"<?php echo esc_attr( $this->fraud_net->sourceWebsiteId() ); ?>"}</script>
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
