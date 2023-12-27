<?php
/**
 * The factory the Pay Later messaging configurator configs.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class ConfigFactory.
 */
class ConfigFactory {
	/**
	 * Returns the configurator config for the given old settings.
	 *
	 * @param Settings $settings The settings.
	 */
	public function from_settings( Settings $settings ): array {
		return array(
			$this->location_to_configurator_placement( 'cart' ) => $this->for_location( $settings, 'cart' ),
			$this->location_to_configurator_placement( 'checkout' ) => $this->for_location( $settings, 'checkout' ),
			$this->location_to_configurator_placement( 'product' ) => $this->for_location( $settings, 'product' ),
			$this->location_to_configurator_placement( 'shop' ) => $this->for_location( $settings, 'shop' ),
			$this->location_to_configurator_placement( 'home' ) => $this->for_location( $settings, 'home' ),
		);
	}

	/**
	 * Returns the configurator config for a location.
	 *
	 * @param Settings $settings The settings.
	 * @param string   $location The location name in the old settings.
	 */
	private function for_location( Settings $settings, string $location ): array {
		$selected_locations = $settings->has( 'pay_later_messaging_locations' ) ? $settings->get( 'pay_later_messaging_locations' ) : array();

		$placement = $this->location_to_configurator_placement( $location );
		if ( in_array( $placement, array( 'category', 'homepage' ), true ) ) {
			$config = array(
				'layout' => 'flex',
				'color'  => $this->get_or_default( $settings, "pay_later_{$location}_message_flex_color", 'black', array( 'black', 'blue', 'white', 'white-no-border' ) ),
				'ratio'  => $this->get_or_default( $settings, "pay_later_{$location}_message_flex_ratio", '8x1', array( '8x1', '20x1' ) ),
			);
		} else {
			$config = array(
				'layout'        => 'text',
				'logo-position' => $this->get_or_default( $settings, "pay_later_{$location}_message_position", 'left' ),
				'logo-type'     => $this->get_or_default( $settings, "pay_later_{$location}_message_logo", 'inline' ),
				'text-color'    => $this->get_or_default( $settings, "pay_later_{$location}_message_color", 'black' ),
				'text-size'     => $this->get_or_default( $settings, "pay_later_{$location}_message_text_size", '12' ),

			);
		}

		return array_merge(
			array(
				'status'    => in_array( $location, $selected_locations, true ) ? 'enabled' : 'disabled',
				'placement' => $placement,
			),
			$config
		);
	}

	/**
	 * Converts the location name from the old settings into the configurator placement.
	 *
	 * @param string $location The location name in the old settings.
	 */
	private function location_to_configurator_placement( string $location ): string {
		switch ( $location ) {
			case 'cart':
			case 'checkout':
			case 'product':
				return $location;
			case 'shop':
				return 'category';
			case 'home':
				return 'homepage';
			default:
				return '';
		}
	}

	/**
	 * Returns the settings value or default, if does not exist or not allowed value.
	 *
	 * @param Settings   $settings The settings.
	 * @param string     $key The key.
	 * @param mixed      $default The default value.
	 * @param array|null $allowed_values The list of allowed values, or null if all values are allowed.
	 * @return mixed
	 */
	private function get_or_default( Settings $settings, string $key, $default, ?array $allowed_values = null ) {
		if ( $settings->has( $key ) ) {
			$value = $settings->get( $key );
			if ( ! $allowed_values || in_array( $value, $allowed_values, true ) ) {
				return $value;
			}
		}
		return $default;
	}
}
