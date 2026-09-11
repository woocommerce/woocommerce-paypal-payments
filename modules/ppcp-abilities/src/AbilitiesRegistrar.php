<?php
/**
 * Class AbilitiesRegistrar
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredFunction, PhanUndeclaredClassMethod @phan-suppress-current-line UnusedSuppression -- Abilities API added in WP 6.9; suppression covers the WP 6.8 compat run. @todo Remove when this plugin drops WP <6.9.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities;

use Automattic\WooCommerce\Internal\Abilities\AbilitiesLoader;

/**
 * Registers PayPal Payments abilities via Woo Core's
 * `woocommerce_ability_definition_classes` loader filter (WC 10.9+). On
 * WC < 10.9 the feature silently no-ops. Gated by the
 * `woocommerce_paypal_payments_abilities_enabled` filter (default false) so
 * scaffolding can ship without committing to a final ability shape.
 *
 * @internal This class may be modified, moved or removed in future releases.
 */
class AbilitiesRegistrar {

	/**
	 * Category slug.
	 *
	 * @deprecated 4.1.0 Use AbilityNames::CATEGORY_SLUG. Kept as an alias so
	 *             external code reading this constant keeps working.
	 *
	 * @var string
	 */
	public const CATEGORY_SLUG = AbilityNames::CATEGORY_SLUG;

	/**
	 * Ability classes registered through the WC 10.9 loader. The ::class
	 * constants don't autoload; they resolve only when Woo's loader iterates
	 * them on WC 10.9+.
	 *
	 * @var array<int, class-string>
	 */
	private const ABILITY_CLASSES = array(
		Domain\GetConnectionStatus::class,
		Domain\GetPaymentMethods::class,
		Domain\GetOrderTracking::class,
		Domain\GetPaypalOrder::class,
	);

	/**
	 * Guards against init() re-arming its filter on repeat calls (duplicate
	 * class entries → _doing_it_wrong on every registered slug). init() must
	 * run at/after plugins_loaded so WC 10.9's autoloader is warm before the
	 * loader gate runs.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Optional override for the WC 10.9 loader check, so the registration path
	 * can be exercised without a WooCommerce runtime. Null uses the real check.
	 *
	 * @var callable|null
	 */
	private $loader_gate;

	/**
	 * @param callable|null $loader_gate Returns whether Woo's AbilitiesLoader is available.
	 */
	public function __construct( ?callable $loader_gate = null ) {
		$this->loader_gate = $loader_gate;
	}

	/**
	 * Initialize the abilities registration.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		/**
		 * Filter whether PayPal Payments' Abilities API registrations are active.
		 *
		 * Default false during rollout. Uses the underscore form (a runtime
		 * per-ability switch operators can flip without unloading the module),
		 * not the dot-form `woocommerce.feature-flags.*` module-load gates.
		 *
		 * @since 4.1.0
		 *
		 * @param bool $enabled Whether to register PayPal Payments abilities. Default false.
		 */
		if ( ! apply_filters( 'woocommerce_paypal_payments_abilities_enabled', false ) ) {
			return;
		}

		if ( ! $this->is_loader_available() ) {
			// Abilities require WC 10.9; silently no-op on older versions.
			return;
		}

		$this->initialized = true;

		add_filter( 'woocommerce_ability_definition_classes', array( $this, 'append_classes' ) );
	}

	/**
	 * Filter callback for `woocommerce_ability_definition_classes`.
	 *
	 * @param array $classes Class names accumulated by the loader.
	 * @return array
	 */
	public function append_classes( array $classes ): array {
		return array_merge( $classes, self::ABILITY_CLASSES );
	}

	/**
	 * Permission callback for read abilities. Mirrors the wc/v3/wc_paypal/*
	 * controllers' gate (RestEndpoint::check_permission() returns
	 * current_user_can('manage_woocommerce')).
	 *
	 * @return bool
	 */
	public function can_manage_woocommerce(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Whether WC 10.9's AbilitiesLoader is available (hard gate; WC 10.9 also
	 * implies WP 6.9 / wp_register_ability()).
	 *
	 * @return bool
	 */
	private function is_loader_available(): bool {
		if ( null !== $this->loader_gate ) {
			return (bool) call_user_func( $this->loader_gate );
		}

		return class_exists( AbilitiesLoader::class );
	}
}
