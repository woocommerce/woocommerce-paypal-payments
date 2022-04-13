<?php
/**
 * PayPal Checkout settings importer.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Handles import of settings from PayPal Checkout into PayPal Payments.
 */
class SettingsImporter {

	/**
	 * PPCP settings.
	 *
	 * @var Settings
	 */
	private $ppcp_settings;

	/**
	 * PayPal Checkout database option.
	 *
	 * @var array
	 */
	private $ppec_settings;


	/**
	 * Constructor.
	 *
	 * @param Settings $settings PPCP settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->ppcp_settings = $settings;
		$this->ppec_settings = (array) get_option( PPECHelper::PPEC_SETTINGS_OPTION_NAME, array() );
	}

	/**
	 * Sets up WP hooks to import PayPal Checkout settings into PPCP when needed.
	 *
	 * @return void
	 */
	public function maybe_hook() {
		// Import settings the first time the PPCP option is created.
		if ( PPECHelper::is_gateway_available() && false === get_option( $this->ppcp_settings::KEY ) ) {
			add_action( 'add_option_' . $this->ppcp_settings::KEY, array( $this, 'import_settings' ), 10, 2 );
		}
	}

	/**
	 * Updates PayPal Payments settings with values taken from PayPal Checkout settings.
	 *
	 * @return void
	 */
	public function import_settings() {
		foreach ( $this->get_settings_translated() as $key => $value ) {
			$this->ppcp_settings->set( $key, $value );
			$this->ppcp_settings->persist();
		}
	}

	/**
	 * Determines whether PayPal Checkout is in use.
	 *
	 * @return boolean true if PayPal Checkout is available and correctly configured.
	 */
	private function is_ppec_active() {
		return ! empty( $this->ppec_settings ) && is_callable( 'wc_gateway_ppec' ) && wc_gateway_ppec()->settings->get_active_api_credentials();
	}

	/**
	 * Translates available PayPal Checkout settings to key/value pairs understood by PayPal Payments settings class.
	 *
	 * @return array An array of key => value pairs of PayPal Payments options.
	 */
	private function get_settings_translated() {
		static $context_translations = array(
			''               => 'cart',
			'mark'           => '',
			'single_product' => 'product',
			'mini_cart'      => 'mini-cart',
		);

		static $credit_messaging_translations = array(
			'credit_message_layout'        => 'layout',
			'credit_message_logo'          => 'logo',
			'credit_message_logo_position' => 'position',
			'credit_message_text_color'    => 'color',
			'credit_message_flex_color'    => 'flex_color',
			'credit_message_flex_ratio'    => 'flex_ratio',
		);

		$result = array();

		foreach ( $this->ppec_settings as $option_key => $option_value ) {
			$key   = false;
			$value = false;

			switch ( $option_key ) {
				case 'title':
				case 'description':
				case 'brand_name':
					$key   = $option_key;
					$value = $option_value;

					break;
				case 'invoice_prefix':
					$key   = 'prefix';
					$value = $option_value;

					break;
				case 'landing_page':
					$key   = $option_key;
					$value = strtoupper( $option_value );

					break;
				case 'paymentaction':
					if ( 'authorization' === $option_value ) {
						$key   = 'intent';
						$value = 'authorize';
					}

					break;
				case 'instant_payments':
					$key   = 'payee_preferred';
					$value = wc_string_to_bool( $option_value );

					break;
				case 'debug':
					$key   = 'logging_enabled';
					$value = wc_string_to_bool( $option_value );

					break;
				case 'hide_funding_methods':
					$key   = 'disable_funding';
					$value = array_values(
						array_intersect(
							array_map( 'strtolower', is_array( $option_value ) ? $option_value : array() ),
							array( 'card', 'credit', 'sepa', 'bancontact', 'blik', 'eps', 'giropay', 'ideal', 'mercadopago', 'mybank', 'p24', 'sofort', 'venmo' )
						)
					);

					break;

				case 'cart_checkout_enabled':
					$key   = 'button_cart_enabled';
					$value = wc_string_to_bool( $option_value );

					break;
				case 'mark_enabled':
					$key   = 'button_enabled';
					$value = wc_string_to_bool( $option_value );

					break;
				case 'checkout_on_single_product_enabled':
					$key   = 'button_product_enabled';
					$value = wc_string_to_bool( $option_value );

					break;
				default:
					break;
			}

			if ( ! $key || is_null( $value ) ) {
				continue;
			}

			$result[ $key ] = $value;
		}

		// Mini-cart enabled is tied to cart in PPEC.
		$result['button_mini-cart_enabled'] = isset( $result['button_cart_enabled'] ) ? $result['button_cart_enabled'] : false;

		// PayPal Credit enabled?
		if ( isset( $this->ppec_settings['credit_enabled'] ) && 'no' === $this->ppec_settings['credit_enabled'] ) {
			$result['disable_funding'] = array_merge(
				isset( $result['disable_funding'] ) ? $result['disable_funding'] : array(),
				array( 'credit' )
			);
		}

		foreach ( $context_translations as $old_context => $new_context ) {
			$old_prefix = $old_context ? $old_context . '_' : '';
			$new_prefix = $new_context ? $new_context . '_' : '';

			$use_cart_settings = ( $old_context && ( ! isset( $this->ppec_settings[ $old_context . '_settings_toggle' ] ) || 'yes' !== $this->ppec_settings[ $old_context . '_settings_toggle' ] ) );

			// If context not enabled, skip the rest of the settings.
			if ( isset( $result[ 'button_' . $new_prefix . 'enabled' ] ) && ! $result[ 'button_' . $new_prefix . 'enabled' ] ) {
				continue;
			}

			foreach ( array( 'layout', 'label', 'shape', 'color' ) as $button_prop ) {
				$old_key = ( ( $use_cart_settings || 'color' === $button_prop ) ? '' : $old_prefix ) . 'button_' . $button_prop;
				$new_key = 'button_' . $new_prefix . $button_prop;

				if ( isset( $this->ppec_settings[ $old_key ] ) ) {
					$result[ $new_key ] = $this->ppec_settings[ $old_key ];
				}
			}

			// Handle Pay Later settings.
			if ( 'mini_cart' === $old_context ) {
				continue;
			}

			$skip_messaging = ( $use_cart_settings && isset( $this->ppec_settings['credit_message_enabled'] ) && ( 'yes' !== $this->ppec_settings['credit_message_enabled'] ) );
			$skip_messaging = $skip_messaging || ( ! $use_cart_settings && isset( $this->ppec_settings[ $old_prefix . 'credit_message_enabled' ] ) && ( 'yes' !== $this->ppec_settings[ $old_prefix . 'credit_message_enabled' ] ) );

			$result[ 'message_' . $new_prefix . 'enabled' ] = ! $skip_messaging;

			if ( $skip_messaging ) {
				continue;
			}

			foreach ( $credit_messaging_translations as $old_message_key => $new_message_key ) {
				$old_key = ( $use_cart_settings ? '' : $old_prefix ) . $old_message_key;
				$new_key = 'message_' . $new_prefix . $new_message_key;

				if ( isset( $this->ppec_settings[ $old_key ] ) ) {
					$result[ $new_key ] = $this->ppec_settings[ $old_key ];
				}
			}
		}

		return $result;
	}

}
