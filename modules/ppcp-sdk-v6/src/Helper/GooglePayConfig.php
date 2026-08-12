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
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

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
	private SubscriptionHelper $subscription_helper;

	/**
	 * @var callable(): bool
	 */
	private $is_available;

	public function __construct(
		SettingsProvider $settings_provider,
		SubscriptionHelper $subscription_helper,
		callable $is_available
	) {
		$this->settings_provider   = $settings_provider;
		$this->subscription_helper = $subscription_helper;
		$this->is_available        = $is_available;
	}

	/**
	 * Whether Google Pay should render in the given page context.
	 */
	public function should_render( string $context ): bool {
		return $this->enabled_for_context( $context )
			&& ( $this->is_available )()
			&& $this->is_product_supported( $context );
	}

	/**
	 * Whether GooglePay is enabled in settings for a given location.
	 */
	private function enabled_for_context( string $context ): bool {
		if ( ! $this->settings_provider->googlepay_enabled() ) {
			return false;
		}

		$styling = $this->settings_provider->googlepay_styles( $context );

		return $styling->enabled
			&& in_array( GooglePayGateway::ID, $styling->methods, true );
	}

	/**
	 * Whether the cart/context contains only supported products.
	 * Google Pay cannot be offered for subscription (it has no vaulting).
	 */
	private function is_product_supported( string $context ): bool {
		$contains_subscription = $this->subscription_helper->locations_with_subscription_product();

		// Whether the currently displayed product is supported.
		if ( 'product' === $context ) {
			return false === ( $contains_subscription['product'] ?? false );
		}

		// All non-product pages only need to inspect the cart contents.
		return false === ( $contains_subscription['cart'] ?? false );
	}

	/**
	 * The Google Pay button styling for a page context (product, cart,
	 * checkout, mini-cart). Only meaningful where should_render() is true.
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
