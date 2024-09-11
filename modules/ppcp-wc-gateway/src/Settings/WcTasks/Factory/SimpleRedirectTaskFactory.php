<?php
/**
 * A factory to create simple redirect task.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Factory;

use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Tasks\SimpleRedirectTask;

/**
 * A factory to create simple redirect task.
 */
class SimpleRedirectTaskFactory implements SimpleRedirectTaskFactoryInterface {

	/**
	 * {@inheritDoc}
	 */
	public function create_task( string $id, string $title, string $description, string $redirect_url ): SimpleRedirectTask {
		return new SimpleRedirectTask( $id, $title, $description, $redirect_url );
	}
}
