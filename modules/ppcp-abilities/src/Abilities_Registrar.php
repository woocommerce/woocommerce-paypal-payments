<?php
/**
 * Class Abilities_Registrar
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredFunction, PhanUndeclaredClassMethod @phan-suppress-current-line UnusedSuppression -- Abilities API added in WP 6.9; suppression covers the WP 6.8 compat run. @todo Remove when this plugin drops WP <6.9.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities;

/**
 * Registers WooCommerce PayPal Payments abilities with the WordPress Abilities API.
 *
 * Thin coordinator: holds the ABILITY_CLASSES list and the
 * can_manage_woocommerce() capability helper that mirrors the load-bearing
 * read gate resolved by every wc/v3/wc_paypal/* REST controller (the shared
 * RestEndpoint base class returns current_user_can('manage_woocommerce')).
 *
 * Gated by the `woocommerce_paypal_payments_abilities_enabled` filter
 * (default false) so registration scaffolding can ship without committing
 * to a final ability shape. Operators flip the filter on per-site to enable
 * the surface; the default flips to true once the surface stabilizes.
 *
 * Registration pattern: abilities are registered exclusively via Woo
 * Core's `woocommerce_ability_definition_classes` loader filter
 * (introduced in WC 10.9). On stores running WC < 10.9 the feature
 * silently no-ops — see {@see self::woo_abilities_loader_available()}.
 *
 * @internal This class may be modified, moved or removed in future releases.
 */
class Abilities_Registrar {

	/**
	 * Category slug used for every PayPal Payments ability.
	 *
	 * The `woocommerce` category is owned and registered by WooCommerce
	 * Core (10.9+); plugin ownership lives in the ability namespace, not
	 * the category. Mirrored on Domain\AbstractPpcpAbility::CATEGORY_SLUG so
	 * Domain classes can reference `self::CATEGORY_SLUG` without a
	 * cross-namespace static call.
	 *
	 * @var string
	 */
	public const CATEGORY_SLUG = 'woocommerce';

	/**
	 * Ability definition classes registered through the WC 10.9 loader.
	 *
	 * Every PayPal Payments ability is listed here. The ::class constants
	 * are compile-time strings — referencing them does NOT autoload the
	 * classes. They resolve only when Woo's loader iterates the filter
	 * return value on WC 10.9+.
	 *
	 * @var array<int, class-string>
	 */
	private const ABILITY_CLASSES = array(
		// Filled in subsequent commits — one Domain\<Name>::class per ability.
	);

	/**
	 * Whether init() has already wired its action callbacks.
	 *
	 * Without this guard, repeated calls to init() while the feature filter
	 * is true would each append a fresh add_filter() for the registrar
	 * callbacks, and Woo's loader would iterate the duplicate class entries
	 * causing _doing_it_wrong notices on every already-registered slug.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize the abilities registration.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		/**
		 * Filter whether WooCommerce PayPal Payments' Abilities API registrations are active.
		 *
		 * Default false during rollout — flip per-site to expose the
		 * abilities surface, or globally to true once the shape stabilizes.
		 *
		 * @since 4.1.0
		 *
		 * @param bool $enabled Whether to register PayPal Payments abilities. Default false.
		 */
		if ( ! apply_filters( 'woocommerce_paypal_payments_abilities_enabled', false ) ) {
			return;
		}

		if ( ! self::woo_abilities_loader_available() ) {
			// Abilities feature requires WC 10.9. Silently no-op on older
			// versions; the feature flag is the rollout safety net.
			return;
		}

		self::$initialized = true;

		add_filter( 'woocommerce_ability_definition_classes', array( __CLASS__, 'append_classes' ) );
	}

	/**
	 * Reset the idempotency guard set by init().
	 *
	 * @internal Test-isolation helper. Not part of the public API.
	 *
	 * @return void
	 */
	public static function reset_initialized_for_testing(): void {
		self::$initialized = false;
	}

	/**
	 * Whether WC 10.9's AbilitiesLoader is available.
	 *
	 * Used as a hard gate: on WC < 10.9 the abilities feature silently
	 * no-ops. WC 10.9 also depends on WP 6.9, so wp_register_ability() is
	 * implicitly available wherever the loader exists.
	 *
	 * @return bool
	 */
	private static function woo_abilities_loader_available(): bool {
		return class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' );
	}

	/**
	 * Append PayPal Payments ability classes to Woo Core's loader.
	 *
	 * Filter callback for `woocommerce_ability_definition_classes`.
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public static function append_classes( array $classes ): array {
		return array_merge( $classes, self::ABILITY_CLASSES );
	}

	/**
	 * Permission callback for read abilities.
	 *
	 * Mirrors the wc/v3/wc_paypal/* REST controllers' resolved read gate.
	 * The shared base class
	 * WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint::check_permission()
	 * returns current_user_can('manage_woocommerce') verbatim.
	 *
	 * @return bool
	 */
	public static function can_manage_woocommerce(): bool {
		return current_user_can( 'manage_woocommerce' );
	}
}
