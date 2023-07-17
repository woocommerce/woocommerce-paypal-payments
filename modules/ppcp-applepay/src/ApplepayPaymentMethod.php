<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayPalPaymentMethod
 */
class ApplepayPaymentMethod {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $plugin_settings;

	/**
	 * PayPalPaymentMethod constructor.
	 *
	 * @param Settings $plugin_settings The settings.
	 */
	public function __construct(
		Settings $plugin_settings
	) {
		$this->plugin_settings = $plugin_settings;
	}

	/**
	 * Initializes the class hooks.
	 */
	public function initialize(): void {
		add_filter( 'ppcp_onboarding_options', array( $this, 'add_apple_onboarding_option' ), 10, 1 );
		add_filter(
			'ppcp_partner_referrals_data',
			function ( array $data ): array {
				try {
					$onboard_with_apple = $this->plugin_settings->get( 'ppcp-onboarding-apple' );
					if ( $onboard_with_apple !== '1' ) {
						return $data;
					}
				} catch ( NotFoundException $exception ) {
					return $data;
				}

				if ( in_array( 'PPCP', $data['products'], true ) ) {
					$data['products'][] = 'PAYMENT_METHODS';
				} elseif ( in_array( 'EXPRESS_CHECKOUT', $data['products'], true ) ) {
					$data['products'][0] = 'PAYMENT_METHODS';
				}
				$data['capabilities'][] = 'APPLE_PAY';

				return $data;
			}
		);
	}

	/**
	 * Adds the ApplePay onboarding option.
	 *
	 * @param string $options The options.
	 *
	 * @return string
	 */
	public function add_apple_onboarding_option( $options ): string {
		$checked = '';
		try {
			$onboard_with_apple = $this->plugin_settings->get( 'ppcp-onboarding-apple' );
			if ( $onboard_with_apple === '1' ) {
				$checked = 'checked';
			}
		} catch ( NotFoundException $exception ) {
			$checked = '';
		}

		return $options . '<li><label><input type="checkbox" id="ppcp-onboarding-apple" ' . $checked . '> ' .
			__( 'Onboard with ApplePay', 'woocommerce-paypal-payments' ) . '
		</label></li>';

	}

	/**
	 * Adds all the Ajax actions to perform the whole workflow
	 */
	public function bootstrap_ajax_request(): void {
		add_action(
			'wp_ajax_' . PropertiesDictionary::VALIDATION,
			array( $this, 'validate_merchant' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::VALIDATION,
			array( $this, 'validate_merchant' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::CREATE_ORDER,
			array( $this, 'create_wc_order' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER,
			array( $this, 'create_wc_order' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::CREATE_ORDER_CART,
			array( $this, 'create_wc_order_from_cart' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::CREATE_ORDER_CART,
			array( $this, 'create_wc_order_from_cart' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::UPDATE_SHIPPING_CONTACT,
			array( $this, 'update_shipping_contact' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::UPDATE_SHIPPING_CONTACT,
			array( $this, 'update_shipping_contact' )
		);
		add_action(
			'wp_ajax_' . PropertiesDictionary::UPDATE_SHIPPING_METHOD,
			array( $this, 'update_shipping_method' )
		);
		add_action(
			'wp_ajax_nopriv_' . PropertiesDictionary::UPDATE_SHIPPING_METHOD,
			array( $this, 'update_shipping_method' )
		);
	}
	/**
	 * Method to validate the merchant against Apple system
	 * On fail triggers and option that shows an admin notice showing the error
	 * On success returns the validation data to the script
	 */
	public function validate_merchant(): void {
		// TODO validate merchant.

		// $this->plugin_settings->set('applepay_validated', 'yes');
	}

	/**
	 * Method to validate and update the shipping contact of the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
	 */
	public function update_shipping_contact(): void {
	}

	/**
	 * Method to validate and update the shipping method selected by the user
	 * It updates the amount paying information if needed
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new contact data
	 */
	public function update_shipping_method(): void {
	}

	/**
	 * Method to create a WC order from the data received from the ApplePay JS
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new order data
	 */
	public function create_wc_order(): void {
	}

	/**
	 * Method to create a WC order from the data received from the ApplePay JS
	 * On error returns an array of errors to be handled by the script
	 * On success returns the new order data
	 */
	public function create_wc_order_from_cart(): void {
	}


	/**
	 * Checks if the nonce in the data object is valid
	 *
	 * @return bool|int
	 */
	protected function is_nonce_valid(): bool {
		$nonce = filter_input( INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $nonce ) {
			return false;
		}
		return wp_verify_nonce(
			$nonce,
			'woocommerce-process_checkout'
		);
	}
}
