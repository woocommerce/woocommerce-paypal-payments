<?php
/**
 * Stub of \Automattic\WooCommerce\Abilities\AbilityDefinition for the
 * Brain Monkey unit-test environment.
 *
 * The real interface ships with WooCommerce 10.9. Unit tests run without
 * the full WC install loaded, so this stub lets Domain ability classes
 * autoload during PHPUnit runs. Production behaviour is unchanged — this
 * file is only required from tests/PHPUnit/bootstrap.php and is never
 * loaded inside the plugin's runtime.
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Abilities;

if ( ! interface_exists( __NAMESPACE__ . '\\AbilityDefinition' ) ) {
	interface AbilityDefinition {
		public static function get_name(): string;

		/**
		 * @return array<string, mixed>
		 */
		public static function get_registration_args(): array;
	}
}
