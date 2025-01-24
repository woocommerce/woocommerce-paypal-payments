<?php
/**
 * PayPal Commerce Settings Model
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class SettingsModel
 *
 * Handles the storage and retrieval of PayPal Commerce settings in WordPress options table.
 * Provides methods to get and update settings with proper type casting and default values.
 */
class SettingsModel {
	/**
	 * WordPress option name for storing the settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ppcp_settings';

	/**
	 * Retrieves the formatted settings from WordPress options.
	 *
	 * Loads the raw settings from wp_options table and formats them into a
	 * standardized array structure with proper type casting.
	 *
	 * @return array The formatted settings array.
	 */
	public function get() : array {
		$settings = get_option( self::OPTION_NAME, array() );

		$formatted = array(
			'invoicePrefix'             => $settings['invoice_prefix'] ?? '',
			'authorizeOnly'             => (bool) ( $settings['authorize_only'] ?? false ),
			'captureVirtualOnlyOrders'  => (bool) ( $settings['capture_virtual_only_orders'] ?? false ),
			'savePaypalAndVenmo'        => (bool) ( $settings['save_paypal_and_venmo'] ?? false ),
			'saveCardDetails'           => (bool) ( $settings['save_credit_card_and_debit_card'] ?? false ),
			'payNowExperience'          => (bool) ( $settings['pay_now_experience'] ?? false ),
			'sandboxAccountCredentials' => (bool) ( $settings['sandbox_account_credentials'] ?? false ),
			'sandboxMode'               => $settings['sandbox_mode'] ?? null,
			'sandboxEnabled'            => (bool) ( $settings['sandbox_enabled'] ?? false ),
			'sandboxClientId'           => $settings['sandbox_client_id'] ?? '',
			'sandboxSecretKey'          => $settings['sandbox_secret_key'] ?? '',
			'sandboxConnected'          => (bool) ( $settings['sandbox_connected'] ?? false ),
			'logging'                   => (bool) ( $settings['logging'] ?? false ),
			'subtotalAdjustment'        => $settings['subtotal_mismatch_fallback'] ?? null,
			'brandName'                 => $settings['brand_name'] ?? '',
			'softDescriptor'            => $settings['soft_descriptor'] ?? '',
			'landingPage'               => $settings['paypal_landing_page'] ?? null,
			'buttonLanguage'            => $settings['button_language'] ?? '',
		);

		return $formatted;
	}

	/**
	 * Updates the settings in WordPress options.
	 *
	 * Converts the provided data array from camelCase to snake_case format
	 * and saves it to wp_options table. Throws an exception if update fails.
	 *
	 * @param array $data The settings data to update.
	 * @return void
	 * @throws RuntimeException When the settings update fails.
	 */
	public function update( array $data ) : void {
		$settings = array(
			'invoice_prefix'                  => $data['invoicePrefix'] ?? '',
			'authorize_only'                  => (bool) ( $data['authorizeOnly'] ?? false ),
			'capture_virtual_only_orders'     => (bool) ( $data['captureVirtualOnlyOrders'] ?? false ),
			'save_paypal_and_venmo'           => (bool) ( $data['savePaypalAndVenmo'] ?? false ),
			'save_credit_card_and_debit_card' => (bool) ( $data['saveCardDetails'] ?? false ),
			'pay_now_experience'              => (bool) ( $data['payNowExperience'] ?? false ),
			'sandbox_account_credentials'     => (bool) ( $data['sandboxAccountCredentials'] ?? false ),
			'sandbox_mode'                    => $data['sandboxMode'] ?? null,
			'sandbox_enabled'                 => (bool) ( $data['sandboxEnabled'] ?? false ),
			'sandbox_client_id'               => $data['sandboxClientId'] ?? '',
			'sandbox_secret_key'              => $data['sandboxSecretKey'] ?? '',
			'sandbox_connected'               => (bool) ( $data['sandboxConnected'] ?? false ),
			'logging'                         => (bool) ( $data['logging'] ?? false ),
			'subtotal_mismatch_fallback'      => $data['subtotalAdjustment'] ?? null,
			'brand_name'                      => $data['brandName'] ?? '',
			'soft_descriptor'                 => $data['softDescriptor'] ?? '',
			'paypal_landing_page'             => $data['landingPage'] ?? null,
			'button_language'                 => $data['buttonLanguage'] ?? '',
		);

		$result = update_option( self::OPTION_NAME, $settings );

		if ( ! $result ) {
			throw new RuntimeException( 'Failed to update settings' );
		}
	}
}
