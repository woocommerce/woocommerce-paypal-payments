<?php
/**
 * Represents the Task for simple redirection. See "Things to do next" WC section.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Tasks;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;

/**
 * Class SimpleRedirectTask
 */
class SimpleRedirectTask extends Task {

	/**
	 * The task ID.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * The task title.
	 *
	 * @var string
	 */
	protected string $title;

	/**
	 * The task description.
	 *
	 * @var string
	 */
	protected string $description;

	/**
	 * The redirection URL.
	 *
	 * @var string
	 */
	protected string $redirect_url;

	/**
	 * SimpleRedirectTask constructor.
	 *
	 * @param string $id The task ID.
	 * @param string $title The task title.
	 * @param string $description The task description.
	 * @param string $redirect_url The redirection URL.
	 */
	public function __construct( string $id, string $title, string $description, string $redirect_url ) {
		parent::__construct();

		$this->id           = $id;
		$this->title        = $title;
		$this->description  = $description;
		$this->redirect_url = $redirect_url;
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * The task title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * The task content.
	 *
	 * @return string
	 */
	public function get_content(): string {
		return '';
	}

	/**
	 * The task time.
	 *
	 * @return string
	 */
	public function get_time(): string {
		return $this->description;
	}

	/**
	 * The task redirection URL.
	 *
	 * @return string
	 */
	public function get_action_url(): string {
		return $this->redirect_url;
	}

	/**
	 * The task completion.
	 *
	 * We need to set the task completed when the redirection happened for the first time.
	 * So this method of a parent class should be overridden.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return parent::is_visited();
	}
}
