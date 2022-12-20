<?php
/**
 * Clears the plugin related data from DB.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

/**
 * Class ClearDatabase
 */
class ClearDatabase implements ClearDatabaseInterface {

	/**
	 * {@inheritDoc}
	 */
	public function delete_options( array $option_names ):void {
		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function clear_scheduled_actions( array $action_names ):void {
		foreach ( $action_names as $action_name ) {
			as_unschedule_action( $action_name );
		}
	}
}
