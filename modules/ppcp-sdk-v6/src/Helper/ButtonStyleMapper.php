<?php
/**
 * Maps admin button settings to v6 Web Component styles.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class ButtonStyleMapper
 */
class ButtonStyleMapper {

	/**
	 * Maps v5 color names to v6 CSS class names.
	 */
	private const COLOR_MAP = array(
		'gold'   => 'paypal-gold',
		'blue'   => 'paypal-blue',
		'white'  => 'paypal-white',
		'black'  => 'paypal-black',
		'silver' => 'paypal-white',
	);

	/**
	 * Maps v5 shape names to v6 border-radius values.
	 */
	private const SHAPE_MAP = array(
		'pill' => '24px',
		'rect' => '4px',
	);

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * ButtonStyleMapper constructor.
	 *
	 * @param Settings $settings The settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns v6-compatible styles for a given context.
	 *
	 * @param string $context The page context (product, cart, checkout, mini-cart).
	 * @return array{colorClass: string, borderRadius: string, height: string}
	 */
	public function styles_for_context( string $context ): array {
		$enable_per_location = $this->settings->has( 'smart_button_enable_styling_per_location' )
			&& $this->settings->get( 'smart_button_enable_styling_per_location' );

		$settings_context = $enable_per_location ? $context : 'general';

		$color  = $this->get_setting( "button_{$settings_context}_color", 'gold' );
		$shape  = $this->get_setting( "button_{$settings_context}_shape", 'pill' );
		$height = $this->get_setting( "button_{$settings_context}_height", 0 );

		return array(
			'colorClass'   => self::COLOR_MAP[ $color ] ?? 'paypal-gold',
			'borderRadius' => self::SHAPE_MAP[ $shape ] ?? '24px',
			'height'       => $height ? $height . 'px' : '',
		);
	}

	/**
	 * Gets a setting value with fallback.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $default The default value.
	 * @return mixed
	 */
	private function get_setting( string $key, $default ) {
		if ( $this->settings->has( $key ) ) {
			return $this->settings->get( $key );
		}

		// Fallback to general if context-specific not found.
		$general_key = preg_replace( '/button_[a-z-]+_/', 'button_general_', $key );
		if ( $general_key && $this->settings->has( $general_key ) ) {
			return $this->settings->get( $general_key );
		}

		return $default;
	}
}
