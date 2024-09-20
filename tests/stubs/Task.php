<?php
declare(strict_types=1);

namespace Automattic\WooCommerce\Admin\Features\OnboardingTasks;

use Automattic\WooCommerce\Internal\Admin\WCAdminUser;

abstract class Task
{

	/**
	 * Constructor
	 *
	 * @param TaskList|null $task_list Parent task list.
	 */
	public function __construct( $task_list = null ) {
		$this->task_list = $task_list;
	}

	/**
	 * ID.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Title.
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Content.
	 *
	 * @return string
	 */
	abstract public function get_content();

	/**
	 * Time.
	 *
	 * @return string
	 */
	abstract public function get_time();
}
