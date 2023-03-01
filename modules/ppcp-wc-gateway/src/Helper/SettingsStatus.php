<?php
/**
 * Helper to get settings status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class SettingsStatus
 */
class SettingsStatus {

	/**
	 * The Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * SettingsStatus constructor.
	 *
	 * @param Settings $settings The Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Check whether Pay Later message is enabled either for checkout, cart or product page.
	 *
	 * @return bool true if is enabled, otherwise false.
	 */
	public function is_pay_later_messaging_enabled(): bool {
		$messaging_enabled  = $this->settings->has( 'pay_later_messaging_enabled' ) && $this->settings->get( 'pay_later_messaging_enabled' );
		$selected_locations = $this->settings->has( 'pay_later_messaging_locations' ) ? $this->settings->get( 'pay_later_messaging_locations' ) : array();

		return $messaging_enabled && ! empty( $selected_locations );
	}

	/**
	 * Check whether Pay Later message is enabled for a given location.
	 *
	 * @param string $location The location setting name.
	 * @return bool true if is enabled, otherwise false.
	 */
	public function is_pay_later_messaging_enabled_for_location( string $location ): bool {
		return $this->is_pay_later_messaging_enabled() && $this->is_enabled_for_location( 'pay_later_messaging_locations', $location );
	}

	/**
	 * Check whether Pay Later button is enabled either for checkout, cart or product page.
	 *
	 * @return bool true if is enabled, otherwise false.
	 */
	public function is_pay_later_button_enabled(): bool {
		$pay_later_button_enabled = $this->settings->has( 'pay_later_button_enabled' ) && $this->settings->get( 'pay_later_button_enabled' );
		$selected_locations       = $this->settings->has( 'pay_later_button_locations' ) ? $this->settings->get( 'pay_later_button_locations' ) : array();

		return $pay_later_button_enabled && ! empty( $selected_locations );
	}

	/**
	 * Check whether Pay Later button is enabled for a given location.
	 *
	 * @param string $location The location.
	 * @return bool true if is enabled, otherwise false.
	 */
	public function is_pay_later_button_enabled_for_location( string $location ): bool {
		return $this->is_pay_later_button_enabled() &&
			( $this->is_enabled_for_location( 'pay_later_button_locations', $location ) ||
				( 'product' === $location && $this->is_enabled_for_location( 'pay_later_button_locations', 'mini-cart' ) ) );
	}

	/**
	 * Checks whether smart buttons are enabled for a given location.
	 *
	 * @param string $location The location.
	 * @return bool true if is enabled, otherwise false.
	 */
	public function is_smart_button_enabled_for_location( string $location ): bool {
		return $this->is_enabled_for_location( 'smart_button_locations', $location );
	}

	/**
	 * Adapts the context value to match the location settings.
	 *
	 * @param string $location The location/context.
	 * @return string
	 */
	protected function normalize_location( string $location ): string {
		if ( 'pay-now' === $location ) {
			$location = 'checkout';
		}
		return $location;
	}

	/**
	 * Checks whether the locations field in the settings includes the given location.
	 *
	 * @param string $setting_name The name of the enabled locations field in the settings.
	 * @param string $location The location.
	 * @return bool
	 */
	protected function is_enabled_for_location( string $setting_name, string $location ): bool {
		$location = $this->normalize_location( $location );

		$selected_locations = $this->settings->has( $setting_name ) ? $this->settings->get( $setting_name ) : array();

		if ( empty( $selected_locations ) ) {
			return false;
		}

		return in_array( $location, $selected_locations, true );
	}
}
