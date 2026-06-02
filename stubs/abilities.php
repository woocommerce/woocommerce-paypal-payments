<?php
/**
 * Static-analysis stub for the WordPress Abilities API + WooCommerce 10.9
 * AbilityDefinition surface.
 *
 * The real interface ships with WooCommerce 10.9 under
 * Automattic\WooCommerce\Abilities\AbilityDefinition; the loader lives at
 * Automattic\WooCommerce\Internal\Abilities\AbilitiesLoader. Neither is
 * present in the woocommerce-stubs package the plugin pulls in for static
 * analysis (woocommerce-stubs tracks the latest stable WC, currently
 * 9.x). This stub keeps PHPStan/Phan happy until woocommerce-stubs grows
 * 10.9 coverage.
 *
 * Production behavior is unaffected — this file is loaded only by
 * phpstan.neon's `scanFiles`, not by the plugin's runtime autoloader.
 *
 * @package WooCommerce\PayPalCommerce
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Abilities {
	if ( ! interface_exists( __NAMESPACE__ . '\\AbilityDefinition' ) ) {
		interface AbilityDefinition {
			/**
			 * The ability slug, including its plugin namespace
			 * (e.g. "woocommerce-paypal-payments/get-connection-status").
			 */
			public static function get_name(): string;

			/**
			 * Args passed verbatim to wp_register_ability() by the loader.
			 *
			 * @return array<string, mixed>
			 */
			public static function get_registration_args(): array;
		}
	}
}

namespace Automattic\WooCommerce\Internal\Abilities {
	if ( ! class_exists( __NAMESPACE__ . '\\AbilitiesLoader' ) ) {
		/**
		 * Stub of the WC 10.9 internal loader. The plugin's
		 * AbilitiesRegistrar only checks that this class_exists() — it
		 * never calls into it.
		 */
		class AbilitiesLoader {
		}
	}
}
