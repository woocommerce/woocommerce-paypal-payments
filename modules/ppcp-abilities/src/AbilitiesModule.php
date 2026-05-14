<?php
/**
 * The abilities module.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Wires the AbilitiesRegistrar coordinator into the plugin lifecycle.
 *
 * Per-ability registration is gated behind the
 * `woocommerce_paypal_payments_abilities_enabled` feature flag (default
 * false) and the WC 10.9 AbilitiesLoader presence check inside
 * AbilitiesRegistrar::init() — see modules/ppcp-abilities/src/AbilitiesRegistrar.php.
 */
class AbilitiesModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 *
	 * The injected $c container is intentionally unused — AbilitiesRegistrar
	 * is a static coordinator (it has no per-request state to receive via
	 * DI) and the Shape-3 Domain abilities resolve their backing services
	 * lazily at execute()-time via PPCP::container() to avoid eagerly
	 * touching services before the rest of the plugin has wired itself up.
	 *
	 * @param ContainerInterface $c A services container instance (unused — see above).
	 */
	public function run( ContainerInterface $c ): bool {
		AbilitiesRegistrar::init();

		return true;
	}
}
