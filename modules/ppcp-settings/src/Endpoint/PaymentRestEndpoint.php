<?php
/**
 * REST endpoint to manage the payment methods page.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;

/**
 * REST controller for the "Payment Methods" settings tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class PaymentRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'payment';

	/**
	 * The settings instance.
	 *
	 * @var PaymentSettings
	 */
	protected PaymentSettings $settings;

	/**
	 * Field mapping for request to profile transformation.
	 *
	 * @var array
	 */
	private array $field_map = array(
		'paypal_show_logo'           => array(
			'js_name'  => 'paypalShowLogo',
			'sanitize' => 'to_boolean',
		),
		'three_d_secure'             => array(
			'js_name'  => 'threeDSecure',
			'sanitize' => 'sanitize_text_field',
		),
		'fastlane_cardholder_name'   => array(
			'js_name'  => 'fastlaneCardholderName',
			'sanitize' => 'to_boolean',
		),
		'fastlane_display_watermark' => array(
			'js_name'  => 'fastlaneDisplayWatermark',
			'sanitize' => 'to_boolean',
		),
	);

	/**
	 * Constructor.
	 *
	 * @param PaymentSettings $settings The settings instance.
	 */
	public function __construct( PaymentSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Field mapping for request to profile transformation.
	 *
	 * @return array[]
	 */
	protected function gateways():array {
		return array(
			// PayPal checkout.
			PayPalGateway::ID         => array(
				'id'              => PayPalGateway::ID,
				'title'           => __( 'PayPal', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximize conversion.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-paypal',
				'itemTitle'       => __( 'PayPal', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximize conversion.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( PayPalGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( PayPalGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
					'paypalShowLogo'          => array(
						'type'    => 'toggle',
						'default' => $this->settings->get_paypal_show_logo(),
						'label'   => __( 'Show logo', 'woocommerce-paypal-payments' ),
					),
				),
			),
			'venmo'                   => array(
				'id'              => 'venmo',
				'itemTitle'       => __( 'Venmo', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Offer Venmo at checkout to millions of active users.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-venmo',
			),
			'pay-later'               => array(
				'id'              => 'pay-later',
				'itemTitle'       => __( 'Pay Later', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Get paid in full at checkout while giving your customers the flexibility to pay in installments over time with no late fees.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-paypal',
			),
			CardButtonGateway::ID     => array(
				'id'              => CardButtonGateway::ID,
				'title'           => __(
					'Credit and debit card payments',
					'woocommerce-paypal-payments'
				),
				'description'     => __(
					"Accept all major credit and debit cards - even if your customer doesn't have a PayPal account.",
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-cards',
				'itemTitle'       => __(
					'Credit and debit card payments',
					'woocommerce-paypal-payments'
				),
				'itemDescription' => __(
					"Accept all major credit and debit cards - even if your customer doesn't have a PayPal account.",
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( CardButtonGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( CardButtonGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),

			// Online card Payments.
			CreditCardGateway::ID     => array(
				'id'              => CreditCardGateway::ID,
				'title'           => __(
					'Advanced Credit and Debit Card Payments',
					'woocommerce-paypal-payments'
				),
				'description'     => __(
					"Present custom credit and debit card fields to your payers so they can pay with credit and debit cards using your site's branding.",
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-advanced-cards',
				'itemTitle'       => __(
					'Advanced Credit and Debit Card Payments',
					'woocommerce-paypal-payments'
				),
				'itemDescription' => __(
					"Present custom credit and debit card fields to your payers so they can pay with credit and debit cards using your site's branding.",
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( CreditCardGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( CreditCardGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
					'threeDSecure'            => array(
						'type'        => 'radio',
						'default'     => $this->settings->get_three_d_secure(),
						'label'       => __( '3D Secure', 'woocommerce-paypal-payments' ),
						'description' => __(
							'Authenticate cardholders through their card issuers to reduce fraud and improve transaction security. Successful 3D Secure authentication can shift liability for fraudulent chargebacks to the card issuer.',
							'woocommerce-paypal-payments'
						),
						'options'     => array(
							array(
								'label' => __(
									'No 3D Secure',
									'woocommerce-paypal-payments'
								),
								'value' => 'no-3d-secure',
							),
							array(
								'label' => __(
									'Only when required',
									'woocommerce-paypal-payments'
								),
								'value' => 'only-required-3d-secure',
							),
							array(
								'label' => __(
									'Always require 3D Secure',
									'woocommerce-paypal-payments'
								),
								'value' => 'always-3d-secure',
							),
						),
					),
				),
			),
			AxoGateway::ID            => array(
				'id'              => AxoGateway::ID,
				'title'           => __( 'Fastlane by PayPal', 'woocommerce-paypal-payments' ),
				'description'     => __(
					"Tap into the scale and trust of PayPal's customer network to recognize shoppers and make guest checkout more seamless than ever.",
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-fastlane',
				'itemTitle'       => __( 'Fastlane by PayPal', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					"Tap into the scale and trust of PayPal's customer network to recognize shoppers and make guest checkout more seamless than ever.",
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'        => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( AxoGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription'  => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( AxoGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
					'fastlaneCardholderName'   => array(
						'type'    => 'toggle',
						'default' => $this->settings->get_fastlane_cardholder_name(),
						'label'   => __(
							'Display cardholder name',
							'woocommerce-paypal-payments'
						),
					),
					'fastlaneDisplayWatermark' => array(
						'type'    => 'toggle',
						'default' => $this->settings->get_fastlane_display_watermark(),
						'label'   => __(
							'Display Fastlane Watermark',
							'woocommerce-paypal-payments'
						),
					),
				),
			),
			ApplePayGateway::ID       => array(
				'id'              => ApplePayGateway::ID,
				'title'           => __( 'Apple Pay', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'Allow customers to pay via their Apple Pay digital wallet.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-apple-pay',
				'itemTitle'       => __( 'Apple Pay', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Allow customers to pay via their Apple Pay digital wallet.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( ApplePayGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( ApplePayGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			GooglePayGateway::ID      => array(
				'id'              => GooglePayGateway::ID,
				'title'           => __( 'Google Pay', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'Allow customers to pay via their Google Pay digital wallet.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-google-pay',
				'itemTitle'       => __( 'Google Pay', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Allow customers to pay via their Google Pay digital wallet.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( GooglePayGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( GooglePayGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),

			// Alternative payment methods.
			BancontactGateway::ID     => array(
				'id'              => BancontactGateway::ID,
				'title'           => __( 'Bancontact', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'Bancontact is the most widely used, accepted and trusted electronic payment method in Belgium. Bancontact makes it possible to pay directly through the online payment systems of all major Belgian banks.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-bancontact',
				'itemTitle'       => __( 'Bancontact', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Bancontact is the most widely used, accepted and trusted electronic payment method in Belgium. Bancontact makes it possible to pay directly through the online payment systems of all major Belgian banks.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( BancontactGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( BancontactGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			BlikGateway::ID           => array(
				'id'              => BlikGateway::ID,
				'title'           => __( 'BLIK', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'A widely used mobile payment method in Poland, allowing Polish customers to pay directly via their banking apps. Transactions are processed in PLN.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-blik',
				'itemTitle'       => __( 'BLIK', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'A widely used mobile payment method in Poland, allowing Polish customers to pay directly via their banking apps. Transactions are processed in PLN.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( BlikGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( BlikGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			EPSGateway::ID            => array(
				'id'              => EPSGateway::ID,
				'title'           => __( 'eps', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'An online payment method in Austria, enabling Austrian buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-eps',
				'itemTitle'       => __( 'eps', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'An online payment method in Austria, enabling Austrian buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( EPSGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( EPSGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			IDealGateway::ID          => array(
				'id'              => IDealGateway::ID,
				'title'           => __( 'iDEAL', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'iDEAL is a payment method in the Netherlands that allows buyers to select their issuing bank from a list of options.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-ideal',
				'itemTitle'       => __( 'iDEAL', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'iDEAL is a payment method in the Netherlands that allows buyers to select their issuing bank from a list of options.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( IDealGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( IDealGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			MyBankGateway::ID         => array(
				'id'              => MyBankGateway::ID,
				'title'           => __( 'MyBank', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'A European online banking payment solution primarily used in Italy, enabling customers to make secure bank transfers during checkout. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-mybank',
				'itemTitle'       => __( 'MyBank', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'A European online banking payment solution primarily used in Italy, enabling customers to make secure bank transfers during checkout. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( MyBankGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( MyBankGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			P24Gateway::ID            => array(
				'id'              => P24Gateway::ID,
				'title'           => __( 'Przelewy24', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'A popular online payment gateway in Poland, offering various payment options for Polish customers. Transactions can be processed in PLN or EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-przelewy24',
				'itemTitle'       => __( 'Przelewy24', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'A popular online payment gateway in Poland, offering various payment options for Polish customers. Transactions can be processed in PLN or EUR.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( P24Gateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( P24Gateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			TrustlyGateway::ID        => array(
				'id'              => TrustlyGateway::ID,
				'title'           => __( 'Trustly', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'A European payment method that allows buyers to make payments directly from their bank accounts, suitable for customers across multiple European countries. Supported currencies include EUR, DKK, SEK, GBP, and NOK.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-trustly',
				'itemTitle'       => __( 'Trustly', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'A European payment method that allows buyers to make payments directly from their bank accounts, suitable for customers across multiple European countries. Supported currencies include EUR, DKK, SEK, GBP, and NOK.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( TrustlyGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( TrustlyGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			MultibancoGateway::ID     => array(
				'id'              => MultibancoGateway::ID,
				'title'           => __( 'Multibanco', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'An online payment method in Portugal, enabling Portuguese buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-multibanco',
				'itemTitle'       => __( 'Multibanco', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'An online payment method in Portugal, enabling Portuguese buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( MultibancoGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( MultibancoGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			PayUponInvoiceGateway::ID => array(
				'id'              => PayUponInvoiceGateway::ID,
				'title'           => __( 'Pay upon Invoice', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.',
					'woocommerce-paypal-payments'
				),
				'icon'            => '',
				'itemTitle'       => __( 'Pay upon Invoice', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( PayUponInvoiceGateway::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( PayUponInvoiceGateway::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
			OXXO::ID                  => array(
				'id'              => OXXO::ID,
				'title'           => __( 'OXXO', 'woocommerce-paypal-payments' ),
				'description'     => __(
					'OXXO is a Mexican chain of convenience stores. *Get PayPal account permission to use OXXO payment functionality by contacting us at (+52) 800–925–0304',
					'woocommerce-paypal-payments'
				),
				'icon'            => 'payment-method-oxxo',
				'itemTitle'       => __( 'OXXO', 'woocommerce-paypal-payments' ),
				'itemDescription' => __(
					'OXXO is a Mexican chain of convenience stores. *Get PayPal account permission to use OXXO payment functionality by contacting us at (+52) 800–925–0304',
					'woocommerce-paypal-payments'
				),
				'fields'          => array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $this->getPaymentTitle( OXXO::ID ) ?? '',
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $this->getPaymentDescription( OXXO::ID ) ?? '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
			),
		);
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() : void {
		/**
		 * GET wc/v3/wc_paypal/payment
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_details' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		/**
		 * POST wc/v3/wc_paypal/payment
		 * {
		 *     [gateway_id]: {
		 *         enabled
		 *         title
		 *         description
		 *     }
		 * }
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_details' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Returns all payment methods details.
	 *
	 * @return WP_REST_Response The current payment methods details.
	 */
	public function get_details() : WP_REST_Response {
		$all_gateways = WC()->payment_gateways->payment_gateways();

		$gateway_settings = array();

		foreach ( $this->gateways() as $key => $value ) {
			if ( ! isset( $all_gateways[ $key ] ) ) {
				$gateway_settings[ $key ] = array(
					'id'              => $this->gateways()[ $key ]['id'] ?? '',
					'title'           => $this->gateways()[ $key ]['title'] ?? '',
					'description'     => $this->gateways()[ $key ]['description'] ?? '',
					'enabled'         => false,
					'icon'            => $this->gateways()[ $key ]['icon'] ?? '',
					'itemTitle'       => $this->gateways()[ $key ]['itemTitle'] ?? '',
					'itemDescription' => $this->gateways()[ $key ]['itemDescription'] ?? '',
				);

				if ( isset( $this->gateways()[ $key ]['fields'] ) ) {
					$gateway_settings[ $key ]['fields'] = $this->gateways()[ $key ]['fields'];
				}

				continue;
			}

			$gateway = $all_gateways[ $key ];

			$gateway_settings[ $key ] = array(
				'enabled'         => 'yes' === $gateway->enabled,
				'title'           => str_replace( '&amp;', '&', $gateway->get_title() ),
				'description'     => $gateway->get_description(),
				'id'              => $this->gateways()[ $key ]['id'] ?? $key,
				'icon'            => $this->gateways()[ $key ]['icon'] ?? '',
				'itemTitle'       => $this->gateways()[ $key ]['itemTitle'] ?? '',
				'itemDescription' => $this->gateways()[ $key ]['itemDescription'] ?? '',
			);

			if ( isset( $this->gateways()[ $key ]['fields'] ) ) {
				$gateway_settings[ $key ]['fields'] = $this->gateways()[ $key ]['fields'];
			}
		}

		$gateway_settings['paypalShowLogo']           = $this->settings->get_paypal_show_logo();
		$gateway_settings['threeDSecure']             = $this->settings->get_three_d_secure();
		$gateway_settings['fastlaneCardholderName']   = $this->settings->get_fastlane_cardholder_name();
		$gateway_settings['fastlaneDisplayWatermark'] = $this->settings->get_fastlane_display_watermark();

		return $this->return_success( apply_filters( 'woocommerce_paypal_payments_payment_methods', $gateway_settings ) );
	}

	/**
	 * Updates payment methods details based on the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The updated payment methods details.
	 */
	public function update_details( WP_REST_Request $request ) : WP_REST_Response {
		$all_gateways = WC()->payment_gateways->payment_gateways();

		$request_data = $request->get_params();

		foreach ( $this->gateways() as $key => $value ) {
			// Check if the REST body contains details for this gateway.
			if ( ! isset( $request_data[ $key ] ) || ! isset( $all_gateways[ $key ] ) ) {
				continue;
			}

			$gateway  = $all_gateways[ $key ];
			$new_data = $request_data[ $key ];
			$gateway->init_form_fields();
			$settings = $gateway->settings;

			if ( isset( $new_data['enabled'] ) ) {
				$settings['enabled'] = wc_bool_to_string( $new_data['enabled'] );
				$gateway->enabled    = $settings['enabled'];
			}

			if ( isset( $new_data['title'] ) ) {
				$settings['title'] = sanitize_text_field( $new_data['title'] );
				$gateway->title    = $settings['title'];
			}

			if ( isset( $new_data['description'] ) ) {
				$settings['description'] = wp_kses_post( $new_data['description'] );
				$gateway->description    = $settings['description'];
			}

			$gateway->settings = $settings;
			update_option( $gateway->get_option_key(), $settings );
		}

		$wp_data = $this->sanitize_for_wordpress(
			$request->get_params(),
			$this->field_map
		);

		$this->settings->from_array( $wp_data );
		$this->settings->save();

		return $this->get_details();
	}

	/**
	 * Returns title for the given gateway.
	 *
	 * @param string $id Gateway ID.
	 * @return string
	 */
	private function getPaymentTitle( string $id ): string {
		if ( ! isset( WC()->payment_gateways()->payment_gateways()[ $id ] ) ) {
			return '';
		}

		return WC()->payment_gateways()->payment_gateways()[ $id ]->get_title() ?? '';
	}

	/**
	 * Returns title for the given gateway.
	 *
	 * @param string $id Gateway ID.
	 * @return string
	 */
	private function getPaymentDescription( string $id ): string {
		if ( ! isset( WC()->payment_gateways()->payment_gateways()[ $id ] ) ) {
			return '';
		}

		return WC()->payment_gateways()->payment_gateways()[ $id ]->get_description() ?? '';
	}
}
