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
 * Wires the AbilitiesRegistrar into the plugin lifecycle. Per-ability
 * registration is gated behind the
 * `woocommerce_paypal_payments_abilities_enabled` flag (default false) and the
 * WC 10.9 AbilitiesLoader check inside AbilitiesRegistrar::init().
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
	 * Binds each ability to its container-resolved handler, as a factory rather
	 * than an instance: AbilityHandlers::callback() defers the factory to the
	 * moment an ability is actually executed, so the backing endpoints are
	 * never built on a request that merely registers the surface.
	 *
	 * The map is keyed by AbilityNames constants, never by
	 * Domain\Get*::get_name(). Naming a Domain shell here would autoload it,
	 * and each shell is declared `implements AbilityDefinition` — an interface
	 * that only exists on WC 10.9+. Since run() fires on plugins_loaded, ahead
	 * of both the feature flag and the AbilitiesLoader gate, that autoload
	 * would fatal every request on every store below WC 10.9.
	 *
	 * @param ContainerInterface $c A services container instance.
	 */
	public function run( ContainerInterface $c ): bool {
		$registrar = $c->get( 'abilities.registrar' );
		assert( $registrar instanceof AbilitiesRegistrar );

		AbilityHandlers::set(
			array(
				AbilityNames::GET_CONNECTION_STATUS => static function () use ( $c ) {
					return $c->get( 'abilities.handler.get-connection-status' );
				},
				AbilityNames::GET_PAYMENT_METHODS   => static function () use ( $c ) {
					return $c->get( 'abilities.handler.get-payment-methods' );
				},
				AbilityNames::GET_ORDER_TRACKING    => static function () use ( $c ) {
					return $c->get( 'abilities.handler.get-order-tracking' );
				},
				AbilityNames::GET_PAYPAL_ORDER      => static function () use ( $c ) {
					return $c->get( 'abilities.handler.get-paypal-order' );
				},
			),
			array( $registrar, 'can_manage_woocommerce' )
		);

		$registrar->init();

		return true;
	}
}
