<?php
/**
 * Interface for settings-accessor classes.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

/**
 * Interface for accessing plugin settings (read-only).
 *
 * Implements a WordPress-like settings access signature: `get_value()` is inspired by the
 * `get_option()` core method.
 *
 * In contrast to the `ContainerInterface`, this interface will never throw an Exception.
 */
interface SettingsGetterInterface {
	/**
	 * Get a single value from stored settings.
	 *
	 * @param string $key     The key to retrieve.
	 * @param mixed  $default The default value if the key is not found.
	 *
	 * @return mixed The value or the default.
	 */
	public function get_value( string $key, $default = null );
}
