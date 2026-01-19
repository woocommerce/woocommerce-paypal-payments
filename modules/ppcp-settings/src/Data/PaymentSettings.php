<?php
/**
 * Payment methods settings class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use WC_Payment_Gateway;

/**
 * Class PaymentSettings
 */
class PaymentSettings extends AbstractDataModel {

	/**
	 * Option key where profile details are stored.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'woocommerce-ppcp-data-payment';

	/**
	 * List of WC_Payment_Gateway instances that need to be saved.
	 *
	 * @var WC_Payment_Gateway[]
	 */
	private array $unsaved_gateways = array();

	/**
	 * Get default values for the model.
	 *
	 * @return array
	 */
	protected function get_defaults(): array {
		return array(
			'paypal_show_logo'            => false,
			'cardholder_name'             => false,
			'fastlane_display_watermark'  => false,
			'venmo_enabled'               => false,
			'paylater_enabled'            => false,
			'applepay_button_enabled'     => false,
			'applepay_validated'          => false,
			'applepay_button_type'        => 'plain',
			'applepay_button_color'       => 'black',
			'applepay_button_language'    => '',
			'applepay_checkout_data_mode' => 'use_wc',
			'ppcp_onboarding_apple'       => '',
		);
	}

	/**
	 * Saves the model data to WordPress options.
	 */
	public function save(): void {
		parent::save();

		foreach ( $this->unsaved_gateways as $gateway ) {
			$gateway->settings['enabled']     = $gateway->enabled;
			$gateway->settings['title']       = $gateway->title;
			$gateway->settings['description'] = $gateway->description;

			update_option( $gateway->get_option_key(), $gateway->settings );
		}

		$this->unsaved_gateways = array();
	}

	/**
	 * Enables or disables the defined payment method, if it exists.
	 *
	 * @param string $method_id  ID of the payment method.
	 * @param bool   $is_enabled Whether to enable the method.
	 */
	public function toggle_method_state( string $method_id, bool $is_enabled ): void {
		switch ( $method_id ) {
			case 'venmo':
				$this->set_venmo_enabled( $is_enabled );
				break;

			case 'pay-later':
				$this->set_paylater_enabled( $is_enabled );
				break;

			default:
				$gateway = $this->get_gateway( $method_id );

				if ( $gateway ) {
					$gateway->enabled = wc_bool_to_string( $is_enabled );

					$this->modified_gateway( $gateway );
				}
		}
	}

	/**
	 * Checks, if the provided payment method is enabled.
	 *
	 * @param string $method_id ID of the payment method.
	 * @return bool True, if the method is enabled. False if it's disabled or not existing.
	 */
	public function is_method_enabled( string $method_id ): bool {
		switch ( $method_id ) {
			case 'venmo':
				return $this->get_venmo_enabled();

			case 'pay-later':
				return $this->get_paylater_enabled();

			default:
				$option_key = 'woocommerce_' . $method_id . '_settings';
				$settings   = get_option( $option_key, array() );

				if ( ! empty( $settings ) && isset( $settings['enabled'] ) ) {
					return wc_string_to_bool( $settings['enabled'] );
				}

				return false;
		}
	}

	/**
	 * Updates the payment method title.
	 *
	 * @param string $method_id ID of the payment method.
	 * @param string $title     The new title.
	 * @return void
	 */
	public function set_method_title( string $method_id, string $title ): void {
		$gateway = $this->get_gateway( $method_id );

		if ( $gateway ) {
			$gateway->title = $title;

			$this->modified_gateway( $gateway );
		}
	}

	/**
	 * Updates the payment method description.
	 *
	 * @param string $method_id   ID of the payment method.
	 * @param string $description The new description.
	 * @return void
	 */
	public function set_method_description( string $method_id, string $description ): void {
		$gateway = $this->get_gateway( $method_id );

		if ( $gateway ) {
			$gateway->description = $description;

			$this->modified_gateway( $gateway );
		}
	}

	/**
	 * Gets the payment method title.
	 *
	 * @param string $method_id ID of the payment method.
	 * @param string $default_title Default title to return if method not found.
	 * @return string The method title, or an empty string if not found.
	 */
	public function get_method_title( string $method_id, string $default_title = '' ): string {
		$gateway = $this->get_gateway( $method_id );

		if ( $gateway ) {
			return $gateway->title;
		}

		return $default_title;
	}

	/**
	 * Get PayPal show logo.
	 *
	 * @return bool
	 */
	public function get_paypal_show_logo(): bool {
		return (bool) $this->data['paypal_show_logo'];
	}

	/**
	 * Get cardholder name.
	 *
	 * @return bool
	 */
	public function get_cardholder_name(): bool {
		return (bool) $this->data['cardholder_name'];
	}

	/**
	 * Get Fastlane display watermark.
	 *
	 * @return bool
	 */
	public function get_fastlane_display_watermark(): bool {
		return (bool) $this->data['fastlane_display_watermark'];
	}

	/**
	 * Get Venmo enabled.
	 *
	 * @return bool
	 */
	public function get_venmo_enabled(): bool {
		return (bool) $this->data['venmo_enabled'];
	}

	/**
	 * Get Pay Later enabled.
	 *
	 * @return bool
	 */
	public function get_paylater_enabled(): bool {
		return (bool) $this->data['paylater_enabled'];
	}

	/**
	 * Set PayPal show logo.
	 *
	 * @param bool $value The value.
	 * @return void
	 */
	public function set_paypal_show_logo( bool $value ): void {
		$this->data['paypal_show_logo'] = $value;
	}

	/**
	 * Set cardholder name.
	 *
	 * @param bool $value The value.
	 * @return void
	 */
	public function set_cardholder_name( bool $value ): void {
		$this->data['cardholder_name'] = $value;
	}

	/**
	 * Set Fastlane display watermark.
	 *
	 * @param bool $value The value.
	 * @return void
	 */
	public function set_fastlane_display_watermark( bool $value ): void {
		$this->data['fastlane_display_watermark'] = $value;
	}

	/**
	 * Set Venmo enabled.
	 *
	 * @param bool $value The value.
	 * @return void
	 */
	public function set_venmo_enabled( bool $value ): void {
		$this->data['venmo_enabled'] = $value;
	}

	/**
	 * Set Pay Later enabled.
	 *
	 * @param bool $value The value.
	 * @return void
	 */
	public function set_paylater_enabled( bool $value ): void {
		$this->data['paylater_enabled'] = $value;
	}

	/**
	 * Get the gateway object for the given method ID.
	 *
	 * @param string $method_id ID of the payment method.
	 * @return WC_Payment_Gateway|null
	 */
	private function get_gateway( string $method_id ): ?WC_Payment_Gateway {
		if ( isset( $this->unsaved_gateways[ $method_id ] ) ) {
			return $this->unsaved_gateways[ $method_id ];
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $method_id ] ) ) {
			return null;
		}

		$gateway = $gateways[ $method_id ];
		$gateway->init_form_fields();

		return $gateway;
	}

	/**
	 * Store the gateway object for later saving.
	 *
	 * @param WC_Payment_Gateway $gateway The gateway object.
	 * @return void
	 */
	private function modified_gateway( WC_Payment_Gateway $gateway ): void {
		$this->unsaved_gateways[ $gateway->id ] = $gateway;
	}

	/**
	 * Get Apple Pay button enabled.
	 *
	 * @return bool
	 */
	public function get_applepay_button_enabled(): bool {
		return (bool) $this->data['applepay_button_enabled'];
	}

	/**
	 * Set Apple Pay button enabled.
	 *
	 * @param bool $value The value.
	 * @return void
	 */
	public function set_applepay_button_enabled( bool $value ): void {
		$this->data['applepay_button_enabled'] = $value;
	}

	/**
	 * Whether the domain verification for ApplePay completed successfully.
	 */
	public function get_applepay_validated(): bool {
		return (bool) $this->data['applepay_validated'];
	}

	/**
	 * Whether the domain verification for ApplePay completed successfully.
	 */
	public function set_applepay_validated( bool $value ): void {
		$this->data['applepay_validated'] = $value;
	}

	/**
	 * Get Apple Pay button type.
	 *
	 * @return string
	 */
	public function get_applepay_button_type(): string {
		return (string) $this->data['applepay_button_type'];
	}

	/**
	 * Set Apple Pay button type.
	 *
	 * @param string $value The value.
	 * @return void
	 */
	public function set_applepay_button_type( string $value ): void {
		$this->data['applepay_button_type'] = $value;
	}

	/**
	 * Get Apple Pay button color.
	 *
	 * @return string
	 */
	public function get_applepay_button_color(): string {
		return (string) $this->data['applepay_button_color'];
	}

	/**
	 * Set Apple Pay button color.
	 *
	 * @param string $value The value.
	 * @return void
	 */
	public function set_applepay_button_color( string $value ): void {
		$this->data['applepay_button_color'] = $value;
	}

	/**
	 * Get Apple Pay button language.
	 *
	 * @return string
	 */
	public function get_applepay_button_language(): string {
		return (string) $this->data['applepay_button_language'];
	}

	/**
	 * Set Apple Pay button language.
	 *
	 * @param string $value The value.
	 * @return void
	 */
	public function set_applepay_button_language( string $value ): void {
		$this->data['applepay_button_language'] = $value;
	}

	/**
	 * Get Apple Pay checkout data mode.
	 *
	 * @return string
	 */
	public function get_applepay_checkout_data_mode(): string {
		return (string) $this->data['applepay_checkout_data_mode'];
	}

	/**
	 * Set Apple Pay checkout data mode.
	 *
	 * @param string $value The value.
	 * @return void
	 */
	public function set_applepay_checkout_data_mode( string $value ): void {
		$this->data['applepay_checkout_data_mode'] = $value;
	}

	/**
	 * Get PPCP onboarding Apple.
	 *
	 * @return string
	 */
	public function get_ppcp_onboarding_apple(): string {
		return (string) $this->data['ppcp_onboarding_apple'];
	}

	/**
	 * Set PPCP onboarding Apple.
	 *
	 * @param string $value The value.
	 * @return void
	 */
	public function set_ppcp_onboarding_apple( string $value ): void {
		$this->data['ppcp_onboarding_apple'] = $value;
	}
}
