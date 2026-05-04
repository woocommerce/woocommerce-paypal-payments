<?php
/**
 * WooCommerce Blueprint stubs for PHPStan.
 *
 * @package WooCommerce\PayPalCommerce
 */

namespace Automattic\WooCommerce\Blueprint\Exporters;

interface StepExporter {
	/**
	 * @return \Automattic\WooCommerce\Blueprint\Steps\Step
	 */
	public function export();

	/**
	 * @return string
	 */
	public function get_step_name(): string;
}

interface HasAlias {
	/**
	 * @return string
	 */
	public function get_alias(): string;
}

namespace Automattic\WooCommerce\Blueprint;

interface StepProcessor {
	/**
	 * @param object $schema
	 * @return StepProcessorResult
	 */
	public function process( $schema ): StepProcessorResult;

	/**
	 * @return string
	 */
	public function get_step_class(): string;
}

class StepProcessorResult {
	/**
	 * @param string $step_name
	 * @return static
	 */
	public static function success( string $step_name ): self {
		return new self();
	}

	/**
	 * @param string $message
	 * @return void
	 */
	public function add_error( string $message ): void {}

	/**
	 * @param string $message
	 * @return void
	 */
	public function add_warn( string $message ): void {}

	/**
	 * @param string $message
	 * @return void
	 */
	public function add_info( string $message ): void {}
}

namespace Automattic\WooCommerce\Blueprint\Steps;

class Step {}

class SetSiteOptions extends Step {
	/**
	 * @param array $options
	 */
	public function __construct( array $options = array() ) {}

	/**
	 * @return string
	 */
	public static function get_step_name(): string {
		return 'setSiteOptions';
	}
}
