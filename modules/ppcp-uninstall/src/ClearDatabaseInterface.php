<?php
/**
 * Can delete the options and clear scheduled actions from database.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

use RuntimeException;

interface ClearDatabaseInterface {

	/**
	 * Deletes the given options from database.
	 *
	 * @param string[] $option_names The list of option names.
	 * @throws RuntimeException If problem deleting.
	 */
	public function delete_options( array $option_names ): void;

	/**
	 * Clears the given scheduled actions.
	 *
	 * @param string[] $action_names The list of scheduled action names.
	 * @throws RuntimeException If problem clearing.
	 */
	public function clear_scheduled_actions( array $action_names ): void;

}
