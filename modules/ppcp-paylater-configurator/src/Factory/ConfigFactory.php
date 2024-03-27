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
			'cart'             => $this->for_location( $settings, 'cart' ),
			'checkout'         => $this->for_location( $settings, 'checkout' ),
			'product'          => $this->for_location( $settings, 'product' ),
			'shop'             => $this->for_location( $settings, 'shop' ),
			'home'             => $this->for_location( $settings, 'home' ),
			'woocommerceBlock' => $this->for_location( $settings, 'woocommerceBlock' ),
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

		if ( in_array( $location, array( 'shop', 'home' ), true ) ) {
			$config = array(
				'layout' => $this->get_or_default( $settings, "pay_later_{$location}_message_layout", 'flex' ),
				'color'  => $this->get_or_default( $settings, "pay_later_{$location}_message_flex_color", 'black' ),
				'ratio'  => $this->get_or_default( $settings, "pay_later_{$location}_message_flex_ratio", '8x1' ),
			);
		} elseif ( $location !== 'woocommerceBlock' ) {
			$config = array(
				'layout'        => $this->get_or_default( $settings, "pay_later_{$location}_message_layout", 'text' ),
				'logo-position' => $this->get_or_default( $settings, "pay_later_{$location}_message_position", 'left' ),
				'logo-type'     => $this->get_or_default( $settings, "pay_later_{$location}_message_logo", 'inline' ),
				'text-color'    => $this->get_or_default( $settings, "pay_later_{$location}_message_color", 'black' ),
				'text-size'     => $this->get_or_default( $settings, "pay_later_{$location}_message_text_size", '12' ),

			);
		}

		return array_merge(
			array(
				'status'    => in_array( $location, $selected_locations, true ) ? 'enabled' : 'disabled',
				'placement' => $location,
			),
			$config ?? array()
		);
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
