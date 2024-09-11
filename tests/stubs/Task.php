<?php
declare(strict_types=1);

abstract class Task
{

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
