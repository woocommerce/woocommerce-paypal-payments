<?php
/**
 * PUI integration.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WP_Error;

/**
 * Class PayUponInvoice.
 */
class PayUponInvoice {

	/**
	 * The module URL.
	 *
	 * @var string
	 */
	protected $module_url;

	/**
	 * The FraudNet entity.
	 *
	 * @var FraudNet
	 */
	protected $fraud_net;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * PayUponInvoice constructor.
	 *
	 * @param string          $module_url The module URL.
	 * @param FraudNet        $fraud_net The FraudNet entity.
	 * @param OrderEndpoint   $order_endpoint The order endpoint.
	 * @param LoggerInterface $logger The logger.
	 * @param Settings        $settings The settings.
	 * @param Environment     $environment The environment.
	 */
	public function __construct(
		string $module_url,
		FraudNet $fraud_net,
		OrderEndpoint $order_endpoint,
		LoggerInterface $logger,
		Settings $settings,
		Environment $environment
	) {
		$this->module_url     = $module_url;
		$this->fraud_net      = $fraud_net;
		$this->order_endpoint = $order_endpoint;
		$this->logger         = $logger;
		$this->settings       = $settings;
		$this->environment    = $environment;
	}

	/**
	 * Initializes PUI integration.
	 *
	 * @throws NotFoundException When setting is not found.
	 */
	public function init(): void {
		add_filter(
			'ppcp_partner_referrals_data',
			function ( array $data ): array {
				if ( $this->settings->has( 'ppcp-onboarding-pui' ) && $this->settings->get( 'ppcp-onboarding-pui' ) !== '1' ) {
					return $data;
				}

				if ( in_array( 'PPCP', $data['products'], true ) ) {
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

		add_action(
			'woocommerce_email_before_order_table',
			function( WC_Order $order, bool $sent_to_admin ) {
				if ( ! $sent_to_admin && PayUponInvoiceGateway::ID === $order->get_payment_method() && $order->has_status( 'processing' ) ) {
					$this->logger->info( "Adding Ratepay payment instructions to email for order #{$order->get_id()}." );

					$instructions = $order->get_meta( 'ppcp_ratepay_payment_instructions_payment_reference' );

					$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
					$merchant_name    = $gateway_settings['brand_name'] ?? '';

					$order_date = $order->get_date_created();
					if ( null === $order_date ) {
						$this->logger->error( 'Could not get WC order date for Ratepay payment instructions.' );
						return;
					}

					$order_purchase_date = $order_date->date( 'd-m-Y' );
					$order_time          = $order_date->date( 'H:i:s' );
					$order_date          = $order_date->date( 'd-m-Y H:i:s' );

					$thirty_days_date = strtotime( $order_date . ' +30 days' );
					if ( false === $thirty_days_date ) {
						$this->logger->error( 'Could not create +30 days date from WC order date.' );
						return;
					}
					$order_date_30d = gmdate( 'd-m-Y', $thirty_days_date );

					$payment_reference   = $instructions[0] ?? '';
					$bic                 = $instructions[1]->bic ?? '';
					$bank_name           = $instructions[1]->bank_name ?? '';
					$iban                = $instructions[1]->iban ?? '';
					$account_holder_name = $instructions[1]->account_holder_name ?? '';

					echo wp_kses_post( "<p>Für Ihre Bestellung #{$order->get_id()} ({$order_purchase_date} $order_time) bei {$merchant_name} haben Sie die Zahlung mittels “Rechnungskauf mit Ratepay“ gewählt." );
					echo '<br>Bitte benutzen Sie die folgenden Informationen für Ihre Überweisung:</br>';
					echo wp_kses_post( "<p>Bitte überweisen Sie den Betrag in Höhe von {$order->get_currency()}{$order->get_total()} bis zum {$order_date_30d} auf das unten angegebene Konto. Wichtig: Bitte geben Sie unbedingt als Verwendungszweck {$payment_reference} an, sonst kann die Zahlung nicht zugeordnet werden.</p>" );

					echo '<ul>';
					echo wp_kses_post( "<li>Empfänger: {$account_holder_name}</li>" );
					echo wp_kses_post( "<li>IBAN: {$iban}</li>" );
					echo wp_kses_post( "<li>BIC: {$bic}</li>" );
					echo wp_kses_post( "<li>Name der Bank: {$bank_name}</li>" );
					echo wp_kses_post( "<li>Verwendungszweck: {$payment_reference}</li>" );
					echo '</ul>';

					echo wp_kses_post( "<p>{$merchant_name} hat die Forderung gegen Sie an die PayPal (Europe) S.à r.l. et Cie, S.C.A. abgetreten, die wiederum die Forderung an Ratepay GmbH abgetreten hat. Zahlungen mit schuldbefreiender Wirkung können nur an die Ratepay GmbH geleistet werden.</p>" );

					echo '<p>Mit freundlichen Grüßen';
					echo '<br>';
					echo wp_kses_post( "{$merchant_name}</p>" );
				}
			},
			10,
			3
		);

		add_filter(
			'woocommerce_gateway_description',
			function( string $description, string $id ): string {
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
					$site_country_code = explode( '-', get_bloginfo( 'language' ) )[0] ?? '';

					// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					$button_text = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );

					if ( 'de' === $site_country_code ) {
						echo wp_kses_post(
							'Mit Klicken auf ' . $button_text . ' akzeptieren Sie die <a href="https://www.ratepay.com/legal-payment-terms" target="_blank">Ratepay Zahlungsbedingungen</a> und erklären sich mit der Durchführung einer <a href="https://www.ratepay.com/legal-payment-dataprivacy" target="_blank">Risikoprüfung durch Ratepay</a>, unseren Partner, einverstanden. Sie akzeptieren auch PayPals <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=de_DE&_ga=1.228729434.718583817.1563460395" target="_blank">Datenschutzerklärung</a>. Falls Ihre Transaktion per Kauf auf Rechnung erfolgreich abgewickelt werden kann, wird der Kaufpreis an Ratepay abgetreten und Sie dürfen nur an Ratepay überweisen, nicht an den Händler.'
						);
					} else {
						echo wp_kses_post(
							'By clicking on ' . $button_text . ', you agree to the <a href="https://www.ratepay.com/legal-payment-terms" target="_blank">terms of payment</a> and <a href="https://www.ratepay.com/legal-payment-dataprivacy">performance of a risk check</a> from the payment partner, Ratepay. You also agree to PayPal’s <a href="https://www.paypal.com/de/webapps/mpp/ua/privacy-full?locale.x=eng_DE&_ga=1.267010504.718583817.1563460395">privacy statement</a>. If your request to purchase upon invoice is accepted, the purchase price claim will be assigned to Ratepay, and you may only pay Ratepay, not the merchant.'
						);
					}
					echo '</div>';

					$description .= ob_get_clean() ?: '';
				}

				return $description;
			},
			10,
			2
		);

		add_action(
			'woocommerce_after_checkout_validation',
			function( array $fields, WP_Error $errors ) {
				if ( 'DE' !== $fields['billing_country'] ) {
					$errors->add( 'validation', __( 'Billing country not available.', 'woocommerce-paypal-payments' ) );
				}
			},
			10,
			2
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			function( array $methods ): array {
				$billing_country = filter_input( INPUT_POST, 'country', FILTER_SANITIZE_STRING ) ?? null;
				if ( $billing_country && 'DE' !== $billing_country ) {
					unset( $methods[ PayUponInvoiceGateway::ID ] );
				}

				return $methods;
			}
		);
	}

	/**
	 * Set configuration JSON for FraudNet integration.
	 */
	public function add_parameter_block(): void {
		?>
		<script type="application/json" fncls="fnparams-dede7cc5-15fd-4c75-a9f4-36c430ee3a99"><?php echo wc_esc_json( $this->fraudnet_configuration(), true ); ?></script>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript?>
		<script type="text/javascript" src="https://c.paypal.com/da/r/fb.js"></script>
		<?php
	}

	/**
	 * Registers PUI assets.
	 */
	public function register_assets(): void {
		wp_enqueue_script(
			'ppcp-pay-upon-invoice',
			trailingslashit( $this->module_url ) . 'assets/js/pay-upon-invoice.js',
			array(),
			'1'
		);
	}

	/**
	 * Returns a configuration JSON string.
	 *
	 * @return string
	 */
	private function fraudnet_configuration(): string {
		$config = array(
			'sandbox' => true,
			'f'       => $this->fraud_net->session_id(),
			's'       => $this->fraud_net->source_website_id(),
		);

		if ( ! $this->environment->current_environment_is( Environment::SANDBOX ) ) {
			unset( $config['sandbox'] );
		}

		$encoded = wp_json_encode( $config );
		if ( false === $encoded ) {
			return '';
		}

		return $encoded;
	}
}
