<?php
/**
 * Abstract base class for testing AgenticSchema implementations.
 *
 * Provides common test cases that apply to all schema classes, ensuring consistent
 * behavior across schema implementations.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\TestCase;

abstract class SchemaTestCase extends TestCase {

	/**
	 * Returns the fully qualified class name of the schema being tested.
	 *
	 * @return string Schema class name (e.g., Money::class).
	 */
	abstract protected function get_schema_class(): string;

	/**
	 * Returns a valid data array that should create a valid schema instance.
	 *
	 * @return array Valid input data with all required fields.
	 */
	abstract protected function get_valid_data(): array;

	// === Shared tests that run for all schema classes ===

	/**
	 * Returns valid changes for testing the with() method.
	 *
	 * Default implementation returns empty array. Override in child classes if needed.
	 *
	 * @return array Valid changes to apply.
	 */
	protected function get_valid_changes(): array {
		return array();
	}

	/**
	 * Tests that from_array creates a valid instance without validation issues.
	 */
	public function test_from_array_creates_valid_instance(): void {
		$class    = $this->get_schema_class();
		$instance = $class::from_array( $this->get_valid_data() );

		$this->assertInstanceOf( $class, $instance );
		$this->assertEmpty( $instance->validate(), 'Valid data should not produce validation issues' );
	}

	/**
	 * Tests that to_array returns the original input data unchanged.
	 */
	public function test_to_array_returns_original_data(): void {
		$data     = $this->get_valid_data();
		$class    = $this->get_schema_class();
		$instance = $class::from_array( $data );

		$this->assertSame( $data, $instance->to_array() );
	}

	/**
	 * Tests that with() creates a new instance (immutability).
	 */
	public function test_with_creates_new_instance(): void {
		$class     = $this->get_schema_class();
		$instance1 = $class::from_array( $this->get_valid_data() );
		$instance2 = $instance1->with( array() );

		$this->assertNotSame( $instance1, $instance2, 'with() must return a new instance' );
		$this->assertInstanceOf( $class, $instance2 );
	}

	/**
	 * Tests that with() merges changes correctly.
	 */
	public function test_with_merges_changes(): void {
		$changes = $this->get_valid_changes();
		if ( empty( $changes ) ) {
			$this->markTestSkipped( 'No valid changes defined for this schema' );
		}

		$class     = $this->get_schema_class();
		$data      = $this->get_valid_data();
		$instance1 = $class::from_array( $data );
		$instance2 = $instance1->with( $changes );

		$expected = array_merge( $data, $changes );
		$this->assertSame( $expected, $instance2->to_array() );
	}
}
