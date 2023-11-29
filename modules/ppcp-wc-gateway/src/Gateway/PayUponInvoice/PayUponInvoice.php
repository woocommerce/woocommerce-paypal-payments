<?php
/**
 * PUI integration.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Psr\Log\LoggerInterface;
use WC_Email;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CaptureFactory;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WP_Error;

/**
 * Class PayUponInvoice.
 */
class PayUponInvoice {

	use TransactionIdHandlingTrait;

	/**
	 * The pui order endpoint.
	 *
	 * @var PayUponInvoiceOrderEndpoint
	 */
	protected $pui_order_endpoint;

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
	 * The PUI helper.
	 *
	 * @var PayUponInvoiceHelper
	 */
	protected $pui_helper;

	/**
	 * The onboarding state.
	 *
	 * @var State
	 */
	protected $state;

	/**
	 * Current PayPal settings page id.
	 *
	 * @var string
	 */
	protected $current_ppcp_settings_page_id;

	/**
	 * PUI seller product status.
	 *
	 * @var PayUponInvoiceProductStatus
	 */
	protected $pui_product_status;

	/**
	 * The checkout helper.
	 *
	 * @var CheckoutHelper
	 */
	protected $checkout_helper;

	/**
	 * The capture factory.
	 *
	 * @var CaptureFactory
	 */
	protected $capture_factory;

	/**
	 * PayUponInvoice constructor.
	 *
	 * @param PayUponInvoiceOrderEndpoint $pui_order_endpoint The PUI order endpoint.
	 * @param LoggerInterface             $logger The logger.
	 * @param Settings                    $settings The settings.
	 * @param State                       $state The onboarding state.
	 * @param string                      $current_ppcp_settings_page_id Current PayPal settings page id.
	 * @param PayUponInvoiceProductStatus $pui_product_status The PUI product status.
	 * @param PayUponInvoiceHelper        $pui_helper The PUI helper.
	 * @param CheckoutHelper              $checkout_helper The checkout helper.
	 * @param CaptureFactory              $capture_factory The capture factory.
	 */
	public function __construct(
		PayUponInvoiceOrderEndpoint $pui_order_endpoint,
		LoggerInterface $logger,
		Settings $settings,
		State $state,
		string $current_ppcp_settings_page_id,
		PayUponInvoiceProductStatus $pui_product_status,
		PayUponInvoiceHelper $pui_helper,
		CheckoutHelper $checkout_helper,
		CaptureFactory $capture_factory
	) {
		$this->pui_order_endpoint            = $pui_order_endpoint;
		$this->logger                        = $logger;
		$this->settings                      = $settings;
		$this->state                         = $state;
		$this->current_ppcp_settings_page_id = $current_ppcp_settings_page_id;
		$this->pui_product_status            = $pui_product_status;
		$this->pui_helper                    = $pui_helper;
		$this->checkout_helper               = $checkout_helper;
		$this->capture_factory               = $capture_factory;
	}

	/**
	 * Initializes PUI integration.
	 *
	 * @throws NotFoundException When setting is not found.
	 */
	public function init(): void {
		if ( $this->pui_helper->is_pui_gateway_enabled() ) {
			$this->settings->set( 'fraudnet_enabled', true );
			$this->settings->persist();
		}

		add_filter(
			'ppcp_partner_referrals_option',
			function ( array $option ): array {
				if ( $option['valid'] ) {
					return $option;
				}
				if ( $option['field'] === 'ppcp-onboarding-pui' ) {
					$option['valid'] = true;
					$option['value'] = ( $option['value'] ? '1' : '' );
				}
				return $option;
			}
		);

		add_filter(
			'ppcp_partner_referrals_data',
			function ( array $data ): array {
				try {
					$onboard_with_pui = $this->settings->get( 'ppcp-onboarding-pui' );
					if ( $onboard_with_pui !== '1' ) {
						return $data;
					}
				} catch ( NotFoundException $exception ) {
					return $data;
				}

				$data['business_entity'] = array(
					'business_type' => array(
						'type' => 'PRIVATE_CORPORATION',
					),
					'addresses'     => array(
						array(
							'address_line_1' => WC()->countries->get_base_address(),
							'admin_area_1'   => WC()->countries->get_base_city(),
							'postal_code'    => WC()->countries->get_base_postcode(),
							'country_code'   => WC()->countries->get_base_country(),
							'type'           => 'WORK',
						),
					),
				);

				if ( in_array( 'PPCP', $data['products'], true ) ) {
					$data['products'][] = 'PAYMENT_METHODS';
				} elseif ( in_array( 'EXPRESS_CHECKOUT', $data['products'], true ) ) {
					$data['products'][0] = 'PAYMENT_METHODS';
				}
				$data['capabilities'][] = 'PAY_UPON_INVOICE';

				return $data;
			}
		);

		add_action(
			'ppcp_payment_capture_completed_webhook_handler',
			function ( WC_Order $wc_order, string $order_id ) {
				try {
					if ( $wc_order->get_payment_method() !== PayUponInvoiceGateway::ID ) {
						return;
					}

					$order = $this->pui_order_endpoint->order( $order_id );

					if (
						property_exists( $order, 'payment_source' )
						&& property_exists( $order->payment_source, 'pay_upon_invoice' )
						&& property_exists( $order->payment_source->pay_upon_invoice, 'payment_reference' )
						&& property_exists( $order->payment_source->pay_upon_invoice, 'deposit_bank_details' )
					) {

						$payment_instructions = array(
							$order->payment_source->pay_upon_invoice->payment_reference,
							$order->payment_source->pay_upon_invoice->deposit_bank_details,
						);
						$wc_order->update_meta_data(
							'ppcp_ratepay_payment_instructions_payment_reference',
							$payment_instructions
						);
						$wc_order->save_meta_data();
						$this->logger->info( "Ratepay payment instructions added to order #{$wc_order->get_id()}." );
					}

					$capture   = $this->capture_factory->from_paypal_response( $order->purchase_units[0]->payments->captures[0] );
					$breakdown = $capture->seller_receivable_breakdown();
					if ( $breakdown ) {
						$wc_order->update_meta_data( PayPalGateway::FEES_META_KEY, $breakdown->to_array() );
						$paypal_fee = $breakdown->paypal_fee();
						if ( $paypal_fee ) {
							$wc_order->update_meta_data( 'PayPal Transaction Fee', (string) $paypal_fee->value() );
						}

						$wc_order->save_meta_data();
					}
				} catch ( RuntimeException $exception ) {
					$this->logger->error( $exception->getMessage() );
				}
			},
			10,
			2
		);

		add_action(
			'woocommerce_email_before_order_table',
			/**
			 * WC_Email type removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( WC_Order $order, bool $sent_to_admin, bool $plain_text, $email ) {
				if (
					! $sent_to_admin
					&& PayUponInvoiceGateway::ID === $order->get_payment_method()
					&& $order->has_status( 'processing' )
					&& is_a( $email, WC_Email::class ) && $email->id === 'customer_processing_order'
				) {
					$this->logger->info( "Adding Ratepay payment instructions to email for order #{$order->get_id()}." );

					$instructions = $order->get_meta( 'ppcp_ratepay_payment_instructions_payment_reference' );

					$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
					$merchant_name    = $gateway_settings['brand_name'] ?? '';

					$order_total = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );

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
					echo wp_kses_post( "<p>Bitte überweisen Sie den Betrag in Höhe von {$order_total} bis zum {$order_date_30d} auf das unten angegebene Konto. Wichtig: Bitte geben Sie unbedingt als Verwendungszweck {$payment_reference} an, sonst kann die Zahlung nicht zugeordnet werden.</p>" );

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
			4
		);

		add_filter(
			'woocommerce_gateway_description',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $description, $id ): string {
				if ( ! is_string( $description ) || ! is_string( $id ) ) {
					return $description;
				}

				if ( PayUponInvoiceGateway::ID === $id ) {
					ob_start();

					$site_country_code = explode( '-', get_bloginfo( 'language' ) )[0] ?? '';

					echo '<div style="padding: 20px 0;">';

					woocommerce_form_field(
						'billing_birth_date',
						array(
							'type'     => 'date',
							'label'    => 'de' === $site_country_code ? 'Geburtsdatum' : 'Birth date',
							'class'    => array( 'form-row-wide' ),
							'required' => true,
							'clear'    => true,
						)
					);

					$checkout_fields         = WC()->checkout()->get_checkout_fields();
					$checkout_phone_required = $checkout_fields['billing']['billing_phone']['required'] ?? false;
					if ( ! array_key_exists( 'billing_phone', $checkout_fields['billing'] ) || $checkout_phone_required === false ) {
						woocommerce_form_field(
							'billing_phone',
							array(
								// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
								'label'        => __( 'Phone', 'woocommerce' ),
								'type'         => 'tel',
								'class'        => array( 'form-row-wide' ),
								'validate'     => array( 'phone' ),
								'autocomplete' => 'tel',
								'required'     => true,
							)
						);
					}

					echo '</div><div>';

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
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $fields, WP_Error $errors ) {
				if ( ! is_array( $fields ) ) {
					return;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$payment_method = wc_clean( wp_unslash( $_POST['payment_method'] ?? '' ) );
				if ( PayUponInvoiceGateway::ID !== $payment_method ) {
					return;
				}

				if ( 'DE' !== $fields['billing_country'] ) {
					$errors->add( 'validation', __( 'Billing country not available.', 'woocommerce-paypal-payments' ) );
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$birth_date = wc_clean( wp_unslash( $_POST['billing_birth_date'] ?? '' ) );
				if ( ( $birth_date && is_string( $birth_date ) && ! $this->checkout_helper->validate_birth_date( $birth_date ) ) || $birth_date === '' ) {
					$errors->add( 'validation', __( 'Invalid birth date.', 'woocommerce-paypal-payments' ) );
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$national_number = wc_clean( wp_unslash( $_POST['billing_phone'] ?? '' ) );
				if ( ! $national_number ) {
					$errors->add( 'validation', __( 'Phone field cannot be empty.', 'woocommerce-paypal-payments' ) );
				}
				if ( $national_number ) {
					$numeric_phone_number = preg_replace( '/[^0-9]/', '', $national_number );
					if ( $numeric_phone_number && ! preg_match( '/^[0-9]{1,14}?$/', $numeric_phone_number ) ) {
						$errors->add( 'validation', __( 'Phone number size must be between 1 and 14', 'woocommerce-paypal-payments' ) );
					}
				}
			},
			10,
			2
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $methods ) {
				if (
					! is_array( $methods )
					|| State::STATE_ONBOARDED !== $this->state->current_state()
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					|| ! ( is_checkout() || isset( $_GET['pay_for_order'] ) && $_GET['pay_for_order'] === 'true' )
				) {
					return $methods;
				}

				if (
					! $this->pui_product_status->pui_is_active()
					|| ! $this->pui_helper->is_checkout_ready_for_pui()
				) {
					unset( $methods[ PayUponInvoiceGateway::ID ] );
				}

				if (
					// phpcs:ignore WordPress.Security.NonceVerification
					isset( $_GET['pay_for_order'] ) && $_GET['pay_for_order'] === 'true'
					&& ! $this->pui_helper->is_pay_for_order_ready_for_pui()
				) {
					unset( $methods[ PayUponInvoiceGateway::ID ] );
				}

				return $methods;
			}
		);

		add_action(
			'woocommerce_update_options_checkout_ppcp-pay-upon-invoice-gateway',
			function () {
				$gateway = WC()->payment_gateways()->payment_gateways()[ PayUponInvoiceGateway::ID ];
				if ( $gateway && $gateway->get_option( 'customer_service_instructions' ) === '' ) {
					$gateway->update_option( 'enabled', 'no' );
				}
			}
		);

		add_action(
			'woocommerce_settings_checkout',
			function() {
				if (
				PayUponInvoiceGateway::ID === $this->current_ppcp_settings_page_id
				&& $this->pui_product_status->pui_is_active()
				) {
					$error_messages = array();
					$pui_gateway    = WC()->payment_gateways->payment_gateways()[ PayUponInvoiceGateway::ID ];
					if ( $pui_gateway->get_option( 'brand_name' ) === '' ) {
						$error_messages[] = esc_html__( 'Could not enable gateway because "Brand name" field is empty.', 'woocommerce-paypal-payments' );
					}
					if ( $pui_gateway->get_option( 'logo_url' ) === '' ) {
						$error_messages[] = esc_html__( 'Could not enable gateway because "Logo URL" field is empty.', 'woocommerce-paypal-payments' );
					}
					if ( $pui_gateway->get_option( 'customer_service_instructions' ) === '' ) {
						$error_messages[] = esc_html__( 'Could not enable gateway because "Customer service instructions" field is empty.', 'woocommerce-paypal-payments' );
					}
					if ( count( $error_messages ) > 0 ) {
						$pui_gateway->update_option( 'enabled', 'no' );
						?>
						<div class="notice notice-error">
							<?php
							array_map(
								static function( $message ) {
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo '<p>' . $message . '</p>';
								},
								$error_messages
							)
							?>
						</div>
						<?php
					}
				} elseif ( PayUponInvoiceGateway::ID === $this->current_ppcp_settings_page_id ) {
					$pui_gateway = WC()->payment_gateways->payment_gateways()[ PayUponInvoiceGateway::ID ];
					if ( 'yes' === $pui_gateway->get_option( 'enabled' ) ) {
						$pui_gateway->update_option( 'enabled', 'no' );
						$redirect_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway' );
						wp_safe_redirect( $redirect_url );
						exit;
					}

					printf(
						'<div class="notice notice-error"><p>%1$s</p></div>',
						esc_html__( 'Could not enable gateway because the connected PayPal account is not activated for Pay upon Invoice. Reconnect your account while Onboard with Pay upon Invoice is selected to try again.', 'woocommerce-paypal-payments' )
					);
				}
			}
		);

		add_action(
			'add_meta_boxes',
			function( string $post_type ) {
				/**
				 * Class and function exist in WooCommerce.
				 *
				 * @psalm-suppress UndefinedClass
				 * @psalm-suppress UndefinedFunction
				 */
				$screen = class_exists( CustomOrdersTableController::class ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
					? wc_get_page_screen_id( 'shop-order' )
					: 'shop_order';

				if ( $post_type === $screen ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$post_id = wc_clean( wp_unslash( $_GET['id'] ?? $_GET['post'] ?? '' ) );
					$order   = wc_get_order( $post_id );
					if ( is_a( $order, WC_Order::class ) && $order->get_payment_method() === PayUponInvoiceGateway::ID ) {
						$instructions = $order->get_meta( 'ppcp_ratepay_payment_instructions_payment_reference' );
						if ( $instructions ) {
							add_meta_box(
								'ppcp_pui_ratepay_payment_instructions',
								__( 'RatePay payment instructions', 'woocommerce-paypal-payments' ),
								function() use ( $instructions ) {
									$payment_reference   = $instructions[0] ?? '';
									$bic                 = $instructions[1]->bic ?? '';
									$bank_name           = $instructions[1]->bank_name ?? '';
									$iban                = $instructions[1]->iban ?? '';
									$account_holder_name = $instructions[1]->account_holder_name ?? '';

									echo '<ul>';
									echo wp_kses_post( "<li>Empfänger: {$account_holder_name}</li>" );
									echo wp_kses_post( "<li>IBAN: {$iban}</li>" );
									echo wp_kses_post( "<li>BIC: {$bic}</li>" );
									echo wp_kses_post( "<li>Name der Bank: {$bank_name}</li>" );
									echo wp_kses_post( "<li>Verwendungszweck: {$payment_reference}</li>" );
									echo '</ul>';
								},
								$screen,
								'side',
								'high'
							);
						}
					}
				}
			}
		);
	}
}
