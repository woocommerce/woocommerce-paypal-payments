<?php
/**
 * Registers the tasks inside the "Things to do next" WC section.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Tasks\SimpleRedirectTask;
use WP_Error;

/**
 * Registers the tasks inside the "Things to do next" WC section.
 */
class TaskRegistrar implements TaskRegistrarInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @throws RuntimeException If problem registering.
	 */
	public function register( array $tasks ): void {
		foreach ( $tasks as $task ) {
			if ( $task instanceof SimpleRedirectTask && ! $task->is_enabled() ) {
				return;
			}

			$added_task = TaskLists::add_task( 'extended', $task );
			if ( $added_task instanceof WP_Error ) {
				throw new RuntimeException( $added_task->get_error_message() );
			}
		}
	}
}
