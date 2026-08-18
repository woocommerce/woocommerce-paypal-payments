<?php
/**
 * Maps admin Apple Pay settings to the v6 frontend configuration.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

class ApplePayConfig {

	/**
	 * The <apple-pay-button> custom property expects a CSS length, not an integer.
	 */
	private const RADIUS_MAP = array(
		'pill' => '24px',
		'rect' => '4px',
	);

	private const DEFAULT_RADIUS = '24px';

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
	 * Whether Apple Pay should render in the given page context.
	 */
	public function should_render( string $context ): bool {
		// The product gate inspects the cart, which WooCommerce loads on
		// wp_loaded. Called earlier it silently sees an empty cart and would
		// wrongly report the wallet as supported, so refuse rather than guess.
		if ( ! did_action( 'wp_loaded' ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__(
					'Apple Pay availability cannot be determined before the wp_loaded action has run.',
					'woocommerce-paypal-payments'
				),
				'4.1.3'
			);

			return false;
		}

		return $this->enabled_for_context( $context )
			&& ( $this->is_available )()
			&& $this->is_product_supported( $context );
	}

	/**
	 * Whether Apple Pay is enabled in settings for a given location.
	 */
	private function enabled_for_context( string $context ): bool {
		if ( ! $this->settings_provider->applepay_enabled() ) {
			return false;
		}

		$styling = $this->settings_provider->applepay_styles( $context );

		return $styling->enabled
			&& in_array( ApplePayGateway::ID, $styling->methods, true );
	}

	/**
	 * Whether the cart/context contains only supported products.
	 * Apple Pay cannot be offered for subscriptions (it has no vaulting).
	 */
	private function is_product_supported( string $context ): bool {
		$contains_subscription = $this->subscription_helper->locations_with_subscription_product();

		// The product page is judged by what it displays; every other page by the
		// cart contents.
		$location = 'product' === $context ? 'product' : 'cart';

		return false === ( $contains_subscription[ $location ] ?? false );
	}

	/**
	 * The Apple Pay button styling for a page context (product, cart, checkout,
	 * mini-cart). Only meaningful where should_render() is true.
	 *
	 * @return array{color: string, type: string, language: string, borderRadius: string}
	 */
	public function styles( string $context ): array {
		$styling = $this->settings_provider->applepay_styles( $context );

		// SettingsProvider maps these through filters the Apple Pay module
		// registers, which are absent when that module is not loaded. Mapping again
		// is idempotent and keeps the values valid either way.
		return array(
			'color'        => PropertiesDictionary::map_color( $styling->color ),
			'type'         => PropertiesDictionary::map_type( $styling->label ),
			'language'     => PropertiesDictionary::map_language( $this->settings_provider->applepay_button_language() ),
			'borderRadius' => self::RADIUS_MAP[ $styling->shape ] ?? self::DEFAULT_RADIUS,
		);
	}

	/**
	 * The name shown on the payment sheet and sent with merchant validation.
	 */
	public function display_name(): string {
		return (string) get_bloginfo( 'name' );
	}
}
