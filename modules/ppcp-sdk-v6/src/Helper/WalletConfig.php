<?php
/**
 * Shared logic for the wallet configuration helpers.
 *
 * Both wallets answer "should this render here" identically; only the settings
 * they read and the button styling they emit differ. styles() is deliberately
 * absent from this contract: the return shapes differ (Apple's borderRadius is a
 * CSS length, Google's an integer) and a shared signature would widen to
 * string|int, losing what PHPStan can check at each call site.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

abstract class WalletConfig {

	protected SettingsProvider $settings_provider;
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
	 * Whether the wallet should render in the given page context.
	 */
	public function should_render( string $context ): bool {
		// The product gate inspects the cart, which WooCommerce loads on
		// wp_loaded. Called earlier it silently sees an empty cart and would
		// wrongly report the wallet as supported, so refuse rather than guess.
		if ( ! did_action( 'wp_loaded' ) ) {
			// Not __METHOD__: that would name this base class rather than the
			// wallet the caller asked about.
			_doing_it_wrong(
				static::class . '::should_render',
				esc_html( $this->too_early_notice() ),
				'4.1.3'
			);

			return false;
		}

		return $this->enabled_for_context( $context )
			&& ( $this->is_available )()
			&& $this->is_product_supported( $context );
	}

	/**
	 * Whether the wallet is enabled in settings for a given location.
	 */
	private function enabled_for_context( string $context ): bool {
		if ( ! $this->wallet_enabled() ) {
			return false;
		}

		$styling = $this->wallet_styles( $context );

		return $styling->enabled
			&& in_array( $this->gateway_id(), $styling->methods, true );
	}

	/**
	 * Whether the cart/context contains only supported products.
	 * Neither wallet can be offered for subscriptions (they have no vaulting).
	 */
	private function is_product_supported( string $context ): bool {
		$contains_subscription = $this->subscription_helper->locations_with_subscription_product();

		// The product page is judged by what it displays; every other page by the
		// cart contents.
		$location = 'product' === $context ? 'product' : 'cart';

		return false === ( $contains_subscription[ $location ] ?? false );
	}

	/**
	 * The whole translated notice for should_render() being called too early.
	 *
	 * Returned as one literal rather than composed from a wallet name, so the
	 * existing translations of both sentences stay valid.
	 */
	abstract protected function too_early_notice(): string;

	abstract protected function wallet_enabled(): bool;

	abstract protected function wallet_styles( string $context ): LocationStylingDTO;

	abstract protected function gateway_id(): string;
}
