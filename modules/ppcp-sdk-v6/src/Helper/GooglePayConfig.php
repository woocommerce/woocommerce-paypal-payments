<?php
/**
 * Maps admin Google Pay settings to the v6 frontend configuration.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Googlepay\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;

class GooglePayConfig {

	/**
	 * Google's buttonRadius expects an integer, not a CSS length.
	 */
	private const RADIUS_MAP = array(
		'pill' => 24,
		'rect' => 4,
	);

	private const DEFAULT_RADIUS = 24;

	private SettingsProvider $settings_provider;

	public function __construct( SettingsProvider $settings_provider ) {
		$this->settings_provider = $settings_provider;
	}

	/**
	 * Whether Google Pay should render in the given page context (product,
	 * cart, checkout, mini-cart).
	 *
	 * Enablement is per-location: the gateway can be switched on globally yet
	 * disabled for individual locations, so callers must pass the context they
	 * are about to render into.
	 */
	public function enabled( string $context ): bool {
		if ( ! $this->settings_provider->googlepay_enabled() ) {
			return false;
		}

		$styling = $this->settings_provider->googlepay_styles( $context );

		return $styling->enabled
			&& in_array( GooglePayGateway::ID, $styling->methods, true );
	}

	/**
	 * The Google Pay button styling for a page context (product, cart,
	 * checkout, mini-cart).
	 *
	 * @return array{color: string, type: string, language: string, borderRadius: int}
	 */
	public function styles( string $context ): array {
		$styling = $this->settings_provider->googlepay_styles( $context );

		// SettingsProvider runs these through the Google Pay module's mapping
		// filters, but those only exist while that module is loaded. Mapping
		// here as well is idempotent and keeps the values valid either way.
		$type = PropertiesDictionary::map_type( $styling->label );

		// The mini cart is too narrow for "Buy with G Pay"; v5 makes the same
		// substitution.
		if ( 'mini-cart' === $context && 'buy' === $type ) {
			$type = 'pay';
		}

		return array(
			'color'        => PropertiesDictionary::map_color( $styling->color ),
			'type'         => $type,
			'language'     => PropertiesDictionary::map_language( $this->settings_provider->googlepay_button_language() ),
			'borderRadius' => self::RADIUS_MAP[ $styling->shape ] ?? self::DEFAULT_RADIUS,
		);
	}
}
