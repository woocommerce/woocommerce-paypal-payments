<?php
/**
 * Boots the abilities module the way a WooCommerce < 10.9 store does:
 * with the plugin autoloader only, and WITHOUT
 * Automattic\WooCommerce\Abilities\AbilityDefinition defined.
 *
 * Run as a subprocess by BootWithoutAbilitiesApiTest. It deliberately does not
 * load tests/PHPUnit/bootstrap.php — that bootstrap requires
 * tests/stubs/AbilityDefinition.php, which defines the very interface whose
 * absence this script exists to reproduce.
 *
 * Prints one `key=value` line per check, then OK. Any fatal here is the
 * regression: the four Domain shells are declared `implements
 * AbilityDefinition`, and PHP resolves that interface while linking the class.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

use WooCommerce\PayPalCommerce\Abilities\AbilitiesModule;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

require_once dirname( __DIR__, 4 ) . '/vendor/autoload.php';

/**
 * The module's boot path touches exactly these two WordPress functions, both
 * from AbilitiesRegistrar::init(). Shimmed rather than pulled in from
 * tests/inc/wp_functions.php so this process stays as close as possible to
 * "plugin autoloader and nothing else".
 */
if ( ! function_exists( 'apply_filters' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Shim of a WP core function.
	function apply_filters( string $hook_name, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Shim of a WP core function.
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

echo 'ability_definition_defined=' . ( interface_exists( 'Automattic\WooCommerce\Abilities\AbilityDefinition' ) ? 'yes' : 'no' ) . "\n";

$container = new class() implements ContainerInterface {
	/**
	 * @param string $id Service id.
	 * @return mixed
	 */
	public function get( $id ) {
		if ( 'abilities.registrar' === $id ) {
			return new AbilitiesRegistrar();
		}

		throw new RuntimeException( 'Boot must not resolve ' . $id );
	}

	/**
	 * @param string $id Service id.
	 */
	public function has( $id ): bool {
		return 'abilities.registrar' === $id;
	}
};

echo 'run_returned=' . ( ( new AbilitiesModule() )->run( $container ) ? 'true' : 'false' ) . "\n";

$declared = get_declared_classes();
foreach ( array( 'GetConnectionStatus', 'GetPaymentMethods', 'GetOrderTracking', 'GetPaypalOrder' ) as $shell ) {
	$fqn = 'WooCommerce\\PayPalCommerce\\Abilities\\Domain\\' . $shell;

	echo 'declared_' . $shell . '=' . ( in_array( $fqn, $declared, true ) ? 'yes' : 'no' ) . "\n";
}

echo "OK\n";
