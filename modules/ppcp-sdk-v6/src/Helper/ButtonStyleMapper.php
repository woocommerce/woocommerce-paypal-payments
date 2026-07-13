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

	private const MIN_HEIGHT = 25;
	private const MAX_HEIGHT = 55;

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
		$color  = (string) $this->style_for_context( 'color', $context, 'gold' );
		$shape  = (string) $this->style_for_context( 'shape', $context, 'pill' );
		$height = (int) $this->style_for_context( 'height', $context, 0 );

		if ( $height ) {
			$height = max( self::MIN_HEIGHT, min( self::MAX_HEIGHT, $height ) );
		}

		return array(
			'colorClass'   => self::COLOR_MAP[ $color ] ?? 'paypal-gold',
			'borderRadius' => self::SHAPE_MAP[ $shape ] ?? '24px',
			'height'       => $height ? $height . 'px' : '',
		);
	}

	/**
	 * Determines the style value for a property in a given context.
	 *
	 * Follows the same key scheme as the v5 SmartButton: the per-context
	 * key button_{context}_{style} applies when per-location styling is
	 * enabled, with button_{style} as the general fallback.
	 *
	 * @param string $style The style property (color, shape, height).
	 * @param string $context The page context.
	 * @param mixed  $default The default value.
	 * @return mixed
	 */
	private function style_for_context( string $style, string $context, $default ) {
		$per_location = $this->settings->has( 'smart_button_enable_styling_per_location' )
			&& $this->settings->get( 'smart_button_enable_styling_per_location' );

		if ( $per_location ) {
			$context_key = "button_{$context}_{$style}";
			if ( $this->settings->has( $context_key ) ) {
				return $this->settings->get( $context_key );
			}
		}

		$general_key = "button_{$style}";
		if ( $this->settings->has( $general_key ) ) {
			return $this->settings->get( $general_key );
		}

		return $default;
	}
}
