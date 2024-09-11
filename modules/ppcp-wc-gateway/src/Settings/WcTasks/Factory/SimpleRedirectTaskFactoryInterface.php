<?php
/**
 * Responsible for creating the simple redirect task.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Factory;

use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Tasks\SimpleRedirectTask;

interface SimpleRedirectTaskFactoryInterface {

	/**
	 * Creates the simple redirect task.
	 *
	 * @param string $id The task ID.
	 * @param string $title The task title.
	 * @param string $description The task description.
	 * @param string $redirect_url The redirection URL.
	 * @return SimpleRedirectTask The task.
	 */
	public function create_task( string $id, string $title, string $description, string $redirect_url ): SimpleRedirectTask;
}
