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
	public function from_settings( Settings $settings ): array {
		return array(
			$this->location_to_configurator_placement( 'cart' ) => $this->for_location( $settings, 'cart' ),
			$this->location_to_configurator_placement( 'checkout' ) => $this->for_location( $settings, 'checkout' ),
			$this->location_to_configurator_placement( 'product' ) => $this->for_location( $settings, 'product' ),
			$this->location_to_configurator_placement( 'shop' ) => $this->for_location( $settings, 'shop' ),
			$this->location_to_configurator_placement( 'home' ) => $this->for_location( $settings, 'home' ),
		);
	}

	private function for_location( Settings $settings, string $location ): array {
		$selected_locations = $settings->has( 'pay_later_messaging_locations' ) ? $settings->get( 'pay_later_messaging_locations' ) : array();

		$placement = $this->location_to_configurator_placement( $location );
		if ( in_array( $placement, array( 'category', 'homepage' ) ) ) {
			$config = array(
				'layout' => 'flex',
				'color'  => $this->get_or_default( $settings, "pay_later_{$location}_message_flex_color", 'black' ),
				'ratio'  => $this->get_or_default( $settings, "pay_later_{$location}_message_flex_ratio", '8x1' ),
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
				'status'    => in_array( $location, $selected_locations ) ? 'enabled' : 'disabled',
				'placement' => $placement,
			),
			$config
		);
	}

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

	private function get_or_default( Settings $settings, string $key, $default ) {
		return $settings->has( $key ) ? $settings->get( $key ) : $default;
	}
}
