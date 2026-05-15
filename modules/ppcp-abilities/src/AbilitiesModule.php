<?php
/**
 * The abilities module.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities;

use Throwable;
use WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
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
	 * AbilitiesRegistrar is a static coordinator (no per-request state to
	 * receive via DI), and the Shape-3 Domain abilities resolve their
	 * backing services lazily at execute()-time via PPCP::container() to
	 * avoid eagerly touching services before the rest of the plugin has
	 * wired itself up.
	 *
	 * The one piece of DI we DO want is the plugin's PSR-3 logger. Domain
	 * abilities run AFTER container bootstrap, so we resolve the logger
	 * here once and hand it to AbstractPpcpAbility's static seam so the
	 * runtime error paths flow through wc_get_logger() and the plugin's
	 * configured log handler instead of PHP's `error_log()` sink.
	 *
	 * A NullLogger fallback ensures resolution failure (the logger module
	 * absent, or container partially wired) never blows up the abilities
	 * surface itself.
	 *
	 * @param ContainerInterface $c A services container instance.
	 */
	public function run( ContainerInterface $c ): bool {
		try {
			$logger = $c->get( 'woocommerce.logger.woocommerce' );
		} catch ( Throwable $e ) {
			$logger = new NullLogger();
		}

		AbstractPpcpAbility::set_logger( $logger );

		AbilitiesRegistrar::init();

		return true;
	}
}
