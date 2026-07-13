<?php
/**
 * Maps admin button settings to v6 Web Component styles.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class ButtonStyleMapper
 */
class ButtonStyleMapper {

	/**
	 * Maps admin color names to v6 CSS class names.
	 */
	private const COLOR_MAP = array(
		'gold'   => 'paypal-gold',
		'blue'   => 'paypal-blue',
		'white'  => 'paypal-white',
		'black'  => 'paypal-black',
		'silver' => 'paypal-white',
	);

	/**
	 * Maps admin shape names to v6 border-radius values.
	 */
	private const SHAPE_MAP = array(
		'pill' => '24px',
		'rect' => '4px',
	);

	private const MIN_HEIGHT = 25;
	private const MAX_HEIGHT = 55;

	/**
	 * The styling settings provider (current source of truth for color/shape).
	 *
	 * @var SettingsProvider
	 */
	private SettingsProvider $settings_provider;

	/**
	 * The legacy settings (height only exists there).
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * ButtonStyleMapper constructor.
	 *
	 * @param SettingsProvider $settings_provider The styling settings provider.
	 * @param Settings         $settings The legacy settings.
	 */
	public function __construct( SettingsProvider $settings_provider, Settings $settings ) {
		$this->settings_provider = $settings_provider;
		$this->settings          = $settings;
	}

	/**
	 * Returns v6-compatible styles for a given context.
	 *
	 * Color and shape come from the styling DTOs (the same source the v5
	 * SmartButton reads); height has no DTO field, so it is read from the
	 * legacy per-context/general settings keys and clamped like v5 does.
	 *
	 * @param string $context The page context (product, cart, checkout, mini-cart).
	 * @return array{colorClass: string, borderRadius: string, height: string}
	 */
	public function styles_for_context( string $context ): array {
		$styling = $this->settings_provider->button_styling( $context );
		$color   = $styling->color ?: 'gold';
		$shape   = $styling->shape ?: 'pill';

		$height = (int) $this->legacy_setting( 'height', $context, 0 );
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
	 * Determines a legacy style value for a property in a given context.
	 *
	 * Follows the same key scheme as the v5 SmartButton: the per-context
	 * key button_{context}_{style} applies when per-location styling is
	 * enabled, with button_{style} as the general fallback.
	 *
	 * @param string $style The style property.
	 * @param string $context The page context.
	 * @param mixed  $default The default value.
	 * @return mixed
	 */
	private function legacy_setting( string $style, string $context, $default ) {
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
