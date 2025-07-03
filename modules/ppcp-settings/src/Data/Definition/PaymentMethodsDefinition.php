<?php
/**
 * Payment Methods Definitions
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data\Definition
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;

/**
 * Class PaymentMethodsDefinition
 *
 * Provides a list of all payment methods that are available in the settings UI.
 */
class PaymentMethodsDefinition {
	/**
	 * Data model that manages the payment method configuration.
	 *
	 * @var PaymentSettings
	 */
	private PaymentSettings $settings;

	/**
	 * Data model for the general plugin settings, used to access flags like
	 * "own brand only" to modify the payment method details.
	 *
	 * @var GeneralSettings
	 */
	private GeneralSettings $general_settings;

	/**
	 * Conflict notices for Axo gateway.
	 *
	 * @var array
	 */
	private array $axo_conflicts_notices;

	/**
	 * List of WooCommerce payment gateways.
	 *
	 * @var array|null
	 */
	private ?array $wc_gateways = null;

	/**
	 * Constructor.
	 *
	 * @param PaymentSettings $settings              Payment methods data model.
	 * @param GeneralSettings $general_settings      General plugin settings model.
	 * @param array           $axo_conflicts_notices Conflicts notices for Axo.
	 */
	public function __construct(
		PaymentSettings $settings,
		GeneralSettings $general_settings,
		array $axo_conflicts_notices = array()
	) {
		$this->settings              = $settings;
		$this->general_settings      = $general_settings;
		$this->axo_conflicts_notices = $axo_conflicts_notices;
	}

	/**
	 * Returns the payment method definitions.
	 *
	 * @return array
	 */
	public function get_definitions() : array {
		// Refresh the WooCommerce gateway details before we build the definitions.
		$this->wc_gateways = WC()->payment_gateways()->payment_gateways();
		$all_methods       = array_merge(
			$this->group_paypal_methods(),
			$this->group_card_methods(),
			$this->group_apms(),
		);
		$result            = array();
		foreach ( $all_methods as $method ) {
			$method_id = $method['id'];

			$result[ $method_id ] = $this->build_method_definition(
				$method_id,
				$method['title'],
				$method['description'],
				$method['icon'],
				$method['fields'] ?? array(),
				$method['warningMessages'] ?? array(),
			);
		}

		return $result;
	}

	/**
	 * Returns a new payment method configuration array that contains all
	 * common attributes which must be present in every method definition.
	 *
	 * @param string      $gateway_id                 The payment method ID.
	 * @param string      $title                      Admin-side payment method title.
	 * @param string      $description                Admin-side info about the payment method.
	 * @param string      $icon                       Admin-side icon of the payment method.
	 * @param array|false $fields                     Optional. Additional fields to display in the
	 *                                                edit modal. Setting this to false omits all
	 *                                                fields.
	 * @param array       $warning_messages           Optional. Warning messages to display in the
	 *                                                UI.
	 * @return array Payment method definition.
	 */
	private function build_method_definition(
		string $gateway_id,
		string $title,
		string $description,
		string $icon,
		$fields = array(),
		array $warning_messages = array()
	) : array {
		$gateway = $this->wc_gateways[ $gateway_id ] ?? null;

		$gateway_title       = $gateway ? $gateway->get_title() : $title;
		$gateway_description = $gateway ? $gateway->get_description() : $description;
		$enabled             = $this->settings->is_method_enabled( $gateway_id );
		$config              = array(
			'id'              => $gateway_id,
			'enabled'         => $enabled,
			'title'           => str_replace( '&amp;', '&', $gateway_title ),
			'description'     => $gateway_description,
			'icon'            => $icon,
			'itemTitle'       => $title,
			'itemDescription' => $description,
			'warningMessages' => $warning_messages,
		);

		if ( is_array( $fields ) ) {
			$config['fields'] = array_merge(
				array(
					'checkoutPageTitle'       => array(
						'type'    => 'text',
						'default' => $gateway_title,
						'label'   => __( 'Checkout page title', 'woocommerce-paypal-payments' ),
					),
					'checkoutPageDescription' => array(
						'type'    => 'text',
						'default' => $gateway ? $gateway->get_description() : '',
						'label'   => __( 'Checkout page description', 'woocommerce-paypal-payments' ),
					),
				),
				$fields
			);
		}

		return $config;
	}

	// Payment method groups.

	/**
	 * Define PayPal related payment methods.
	 *
	 * @return array
	 */
	public function group_paypal_methods() : array {
		$group = array(
			array(
				'id'          => PayPalGateway::ID,
				'title'       => __( 'PayPal', 'woocommerce-paypal-payments' ),
				'description' => __( 'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximize conversion.', 'woocommerce-paypal-payments' ),
				'icon'        => 'payment-method-paypal',
				'fields'      => array(
					'paypalShowLogo' => array(
						'type'    => 'toggle',
						'default' => $this->settings->get_paypal_show_logo(),
						'label'   => __( 'Show logo', 'woocommerce-paypal-payments' ),
					),
				),
			),
			array(
				'id'          => 'venmo',
				'title'       => __( 'Venmo', 'woocommerce-paypal-payments' ),
				'description' => __(
					'Offer Venmo at checkout to millions of active users.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-venmo',
				'fields'      => false,
			),
			array(
				'id'          => 'pay-later',
				'title'       => __( 'Pay Later', 'woocommerce-paypal-payments' ),
				'description' => __(
					'Get paid in full at checkout while giving your customers the flexibility to pay in installments over time with no late fees.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-paypal',
				'fields'      => false,
			),
		);

		if ( ! $this->general_settings->own_brand_only() ) {
			$group[] = array(
				'id'          => CardButtonGateway::ID,
				'title'       => __( 'Credit and debit card payments', 'woocommerce-paypal-payments' ),
				'description' => __( "Accept all major credit and debit cards - even if your customer doesn't have a PayPal account . ", 'woocommerce-paypal-payments' ),
				'icon'        => 'payment-method-cards',
			);
		}

		return apply_filters( 'woocommerce_paypal_payments_gateway_group_paypal', $group );
	}

	/**
	 * Define card related payment methods.
	 *
	 * @return array
	 */
	public function group_card_methods() : array {
		$group = array();

		if ( ! $this->general_settings->own_brand_only() ) {
			$group[] = array(
				'id'          => CreditCardGateway::ID,
				'title'       => __( 'Advanced Credit and Debit Card Payments', 'woocommerce-paypal-payments' ),
				'description' => __( "Present custom credit and debit card fields to your payers so they can pay with credit and debit cards using your site's branding.", 'woocommerce-paypal-payments' ),
				'icon'        => 'payment-method-advanced-cards',
				'fields'      => array(),
			);
			$group[] = array(
				'id'              => AxoGateway::ID,
				'title'           => __( 'Fastlane by PayPal', 'woocommerce-paypal-payments' ),
				'description'     => __( "Tap into the scale and trust of PayPal's customer network to recognize shoppers and make guest checkout more seamless than ever.", 'woocommerce-paypal-payments' ),
				'icon'            => 'payment-method-fastlane',
				'fields'          => array(
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
				'warningMessages' => $this->axo_conflicts_notices,
			);
			$group[] = array(
				'id'          => ApplePayGateway::ID,
				'title'       => __( 'Apple Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow customers to pay via their Apple Pay digital wallet.', 'woocommerce-paypal-payments' ),
				'icon'        => 'payment-method-apple-pay',
			);
			$group[] = array(
				'id'          => GooglePayGateway::ID,
				'title'       => __( 'Google Pay', 'woocommerce-paypal-payments' ),
				'description' => __( 'Allow customers to pay via their Google Pay digital wallet.', 'woocommerce-paypal-payments' ),
				'icon'        => 'payment-method-google-pay',
			);
		}

		return apply_filters( 'woocommerce_paypal_payments_gateway_group_cards', $group );
	}

	/**
	 * Builds an array of payment method definitions, which includes details
	 * of all APM gateways.
	 *
	 * @return array List of payment method definitions.
	 */
	public function group_apms() : array {
		$group = array(
			array(
				'id'          => BancontactGateway::ID,
				'title'       => __( 'Bancontact', 'woocommerce-paypal-payments' ),
				'description' => __(
					'Bancontact is the most widely used, accepted and trusted electronic payment method in Belgium. Bancontact makes it possible to pay directly through the online payment systems of all major Belgian banks.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-bancontact',
			),
			array(
				'id'          => BlikGateway::ID,
				'title'       => __( 'BLIK', 'woocommerce-paypal-payments' ),
				'description' => __(
					'A widely used mobile payment method in Poland, allowing Polish customers to pay directly via their banking apps. Transactions are processed in PLN.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-blik',
			),
			array(
				'id'          => EPSGateway::ID,
				'title'       => __( 'eps', 'woocommerce-paypal-payments' ),
				'description' => __(
					'An online payment method in Austria, enabling Austrian buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-eps',
			),
			array(
				'id'          => IDealGateway::ID,
				'title'       => __( 'iDEAL', 'woocommerce-paypal-payments' ),
				'description' => __(
					'iDEAL is a payment method in the Netherlands that allows buyers to select their issuing bank from a list of options.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-ideal',
			),
			array(
				'id'          => MyBankGateway::ID,
				'title'       => __( 'MyBank', 'woocommerce-paypal-payments' ),
				'description' => __(
					'A European online banking payment solution primarily used in Italy, enabling customers to make secure bank transfers during checkout. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-mybank',
			),
			array(
				'id'          => P24Gateway::ID,
				'title'       => __( 'Przelewy24', 'woocommerce-paypal-payments' ),
				'description' => __(
					'A popular online payment gateway in Poland, offering various payment options for Polish customers. Transactions can be processed in PLN or EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-przelewy24',
			),
			array(
				'id'          => TrustlyGateway::ID,
				'title'       => __( 'Trustly', 'woocommerce-paypal-payments' ),
				'description' => __(
					'A European payment method that allows buyers to make payments directly from their bank accounts, suitable for customers across multiple European countries. Supported currencies include EUR, DKK, SEK, GBP, and NOK.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-trustly',
			),
			array(
				'id'          => MultibancoGateway::ID,
				'title'       => __( 'Multibanco', 'woocommerce-paypal-payments' ),
				'description' => __(
					'An online payment method in Portugal, enabling Portuguese buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-multibanco',
			),
			array(
				'id'          => PayUponInvoiceGateway::ID,
				'title'       => __( 'Pay upon Invoice', 'woocommerce-paypal-payments' ),
				'description' => __(
					'Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.',
					'woocommerce-paypal-payments'
				),
				'icon'        => '',
			),
			array(
				'id'          => OXXO::ID,
				'title'       => __( 'OXXO', 'woocommerce-paypal-payments' ),
				'description' => __(
					'OXXO is a Mexican chain of convenience stores. *Get PayPal account permission to use OXXO payment functionality by contacting us at (+52) 800–925–0304',
					'woocommerce-paypal-payments'
				),
				'icon'        => 'payment-method-oxxo',
			),
		);

		return apply_filters( 'woocommerce_paypal_payments_gateway_group_apm', $group );
	}
}
