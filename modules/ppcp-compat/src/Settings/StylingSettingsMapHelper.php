<?php
/**
 * A helper for mapping the old/new styling settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\Settings;

use RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\ContextTrait;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;

/**
 * A map of old to new styling settings.
 *
 * @psalm-import-type newSettingsKey from SettingsMap
 * @psalm-import-type oldSettingsKey from SettingsMap
 */
class StylingSettingsMapHelper {

	use ContextTrait;

	/**
	 * Maps old setting keys to new setting style names.
	 *
	 * The `StylingSettings` class stores settings as `LocationStylingDTO` objects.
	 * This method creates a mapping from old setting keys to the corresponding style names.
	 *
	 * Example:
	 * 'button_product_layout' => 'layout'
	 *
	 * This mapping will allow to retrieve the correct style value
	 * from a `LocationStylingDTO` object by dynamically accessing its properties.
	 *
	 * @psalm-return array<oldSettingsKey, newSettingsKey>
	 */
	public function map(): array {

		$mapped_settings = array(
			'smart_button_locations'                   => '',
			'pay_later_button_locations'               => '',
			'disable_funding'                          => '',
			'googlepay_button_enabled'                 => '',
			'applepay_button_enabled'                  => '',
			'smart_button_enable_styling_per_location' => '',
		);

		foreach ( $this->locations_map() as $old_location_name => $new_location_name ) {
			foreach ( $this->styles() as $style ) {
				$old_styling_key                     = $this->get_old_styling_setting_key( $old_location_name, $style );
				$mapped_settings[ $old_styling_key ] = $style;
			}
		}

		return $mapped_settings;
	}

	/**
	 * Retrieves the value of a mapped key from the new settings.
	 *
	 * @param string               $old_key The key from the legacy settings.
	 * @param LocationStylingDTO[] $styling_models The list of location styling models.
	 *
	 * @return mixed The value of the mapped setting, (null if not found).
	 */
	public function mapped_value( string $old_key, array $styling_models ) {
		switch ( $old_key ) {
			case 'smart_button_locations':
				return $this->mapped_smart_button_locations_value( $styling_models );

			case 'smart_button_enable_styling_per_location':
				return true;

			case 'pay_later_button_locations':
				return $this->mapped_pay_later_button_locations_value( $styling_models );

			case 'disable_funding':
				return $this->mapped_disabled_funding_value( $styling_models );

			case 'googlepay_button_enabled':
				return $this->mapped_google_pay_or_apple_pay_enabled_value( $styling_models, 'googlepay' );

			case 'applepay_button_enabled':
				return $this->mapped_google_pay_or_apple_pay_enabled_value( $styling_models, 'applepay' );

			default:
				foreach ( $this->locations_map() as $old_location_name => $new_location_name ) {
					foreach ( $this->styles() as $style ) {
						if ( $old_key !== $this->get_old_styling_setting_key( $old_location_name, $style ) ) {
							continue;
						}

						$location_settings = $styling_models[ $new_location_name ] ?? false;

						if ( ! $location_settings instanceof LocationStylingDTO ) {
							continue;
						}

						return $location_settings->$style ?? null;
					}
				}
				return null;
		}
	}

	/**
	 * Returns a mapping of old button location names to new settings location names.
	 *
	 * @return string[] The mapping of old location names to new location names.
	 */
	protected function locations_map(): array {
		return array(
			'product'                => 'product',
			'cart'                   => 'cart',
			'cart-block'             => 'cart',
			'checkout'               => 'classic_checkout',
			'mini-cart'              => 'mini_cart',
			'checkout-block-express' => 'express_checkout',
		);
	}

	/**
	 * Returns a mapping of current context to new button location names.
	 *
	 * @return string[] The mapping of current context to new button location names.
	 */
	protected function current_context_to_new_button_location_map(): array {
		return array(
			'product'        => 'product',
			'cart'           => 'cart',
			'cart-block'     => 'cart',
			'checkout'       => 'classic_checkout',
			'pay-now'        => 'classic_checkout',
			'mini-cart'      => 'mini_cart',
			'checkout-block' => 'express_checkout',
		);
	}

	/**
	 * Returns the available style names.
	 *
	 * @return string[] The list of available style names.
	 */
	protected function styles(): array {
		return array(
			'enabled',
			'methods',
			'shape',
			'label',
			'color',
			'layout',
			'tagline',
		);
	}

	/**
	 * Returns the old styling setting key name based on provided location and style names.
	 *
	 * @param string $location The location name.
	 * @param string $style    The style name.
	 * @return string The old styling setting key name.
	 */
	protected function get_old_styling_setting_key( string $location, string $style ): string {
		$location_setting_name_part = $location === 'checkout' ? '' : "_{$location}";
		return "button{$location_setting_name_part}_{$style}";
	}

	/**
	 * Retrieves the mapped smart button locations from the new settings.
	 *
	 * @param LocationStylingDTO[] $styling_models The list of location styling models.
	 * @return string[] The list of enabled smart button locations.
	 */
	protected function mapped_smart_button_locations_value( array $styling_models ): array {
		$enabled_locations = array();
		$locations         = array_flip( $this->locations_map() );

		foreach ( $styling_models as $model ) {
			if ( ! $model->enabled ) {
				continue;
			}

			$enabled_locations[] = $locations[ $model->location ] ?? '';
			if ( $model->location === 'cart' ) {
				$enabled_locations[] = 'cart';
			}
		}

		return $enabled_locations;
	}

	/**
	 * Retrieves the mapped pay later button locations from the new settings.
	 *
	 * @param LocationStylingDTO[] $styling_models The list of location styling models.
	 * @return string[] The list of locations where the Pay Later button is enabled.
	 */
	protected function mapped_pay_later_button_locations_value( array $styling_models ): array {
		$enabled_locations = array();
		$locations         = array_flip( $this->locations_map() );
		foreach ( $styling_models as $model ) {
			if ( ! $model->enabled || ! in_array( 'paylater', $model->methods, true ) ) {
				continue;
			}

			$enabled_locations[] = $locations[ $model->location ] ?? '';
			if ( $model->location === 'cart' ) {
				$enabled_locations[] = 'cart';
			}
		}

		return $enabled_locations;
	}

	/**
	 * Retrieves the mapped disabled funding value from the new settings.
	 *
	 * @param LocationStylingDTO[] $styling_models The list of location styling models.
	 * @return array|null The list of disabled funding, or null if none are disabled.
	 */
	protected function mapped_disabled_funding_value( array $styling_models ): ?array {
		$disabled_funding         = array();
		$locations_to_context_map = $this->current_context_to_new_button_location_map();
		$current_context          = $locations_to_context_map[ $this->context() ] ?? '';

		foreach ( $styling_models as $model ) {
			if ( $model->location !== $current_context || in_array( 'venmo', $model->methods, true ) ) {
				continue;
			}

			$disabled_funding[] = 'venmo';

			return $disabled_funding;
		}

		return null;
	}

	/**
	 * Retrieves the mapped enabled/disabled Google Pay or Apple Pay value from the new settings.
	 *
	 * @param LocationStylingDTO[]   $styling_models The list of location styling models.
	 * @param 'googlepay'|'applepay' $button_name The button name ('googlepay' or 'applepay').
	 * @return int The enabled (1) or disabled (0) state.
	 * @throws RuntimeException If an invalid button name is provided.
	 */
	protected function mapped_google_pay_or_apple_pay_enabled_value( array $styling_models, string $button_name ): ?int {
		if ( $button_name !== 'googlepay' && $button_name !== 'applepay' ) {
			throw new RuntimeException( 'Wrong button name is provided. Either "googlepay" or "applepay" can be used' );
		}

		$locations_to_context_map = $this->current_context_to_new_button_location_map();
		$current_context          = $locations_to_context_map[ $this->context() ] ?? '';

		foreach ( $styling_models as $model ) {
			if ( ! $model->enabled
				|| $model->location !== $current_context
				|| ! in_array( $button_name, $model->methods, true )
			) {
				continue;
			}

			return 1;
		}

		return 0;
	}
}
