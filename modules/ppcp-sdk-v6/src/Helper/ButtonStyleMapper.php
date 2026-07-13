<?php
/**
 * Maps admin button settings to v6 Web Component styles.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;

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

	/**
	 * The styling settings provider.
	 *
	 * @var SettingsProvider
	 */
	private SettingsProvider $settings_provider;

	/**
	 * ButtonStyleMapper constructor.
	 *
	 * @param SettingsProvider $settings_provider The styling settings provider.
	 */
	public function __construct( SettingsProvider $settings_provider ) {
		$this->settings_provider = $settings_provider;
	}

	/**
	 * Returns v6-compatible styles for a given context.
	 *
	 * Color and shape come from the styling DTOs, the same source the v5
	 * SmartButton reads. There is no height: the styling DTOs have no
	 * height field (the legacy button_*_height keys are unmaintained
	 * fossils that current v5 no longer honors either).
	 *
	 * @param string $context The page context (product, cart, checkout, mini-cart).
	 * @return array{colorClass: string, borderRadius: string}
	 */
	public function styles_for_context( string $context ): array {
		$styling = $this->settings_provider->button_styling( $context );
		$color   = $styling->color ?: 'gold';
		$shape   = $styling->shape ?: 'pill';

		return array(
			'colorClass'   => self::COLOR_MAP[ $color ] ?? 'paypal-gold',
			'borderRadius' => self::SHAPE_MAP[ $shape ] ?? '24px',
		);
	}
}
