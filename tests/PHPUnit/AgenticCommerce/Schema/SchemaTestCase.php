<?php
/**
 * Abstract base class for testing AgenticSchema implementations.
 *
 * Provides common test cases and helpers for consistent schema validation testing.
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


	/**
	 * @return array Minimal input to pass schema validation
	 */
	protected function mandatory_data(): array {
		return array();
	}

	// === Shared tests that run for all schema classes ===

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

	// === Helper methods for common test patterns ===

	/**
	 * Tests that a required field produces validation error when missing.
	 *
	 * @param string $field_name Expected validation error field key.
	 * @param array  $extra_data Additional data to include in test.
	 */
	protected function assertRequiredField( string $field_name, array $extra_data = array() ): void {
		$class    = $this->get_schema_class();
		$instance = $class::from_array( $extra_data );
		$issues   = $instance->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Missing value for field '$field_name' should raise a validation issue" );

		$issue_fields = array_map(
			static fn( $issue ) => $issue->to_array()['field'],
			$issues
		);

		$this->assertContains( $field_name, $issue_fields, "Expected validation error for required field: $field_name" );
	}

	/**
	 * Tests that an optional field returns null when missing.
	 *
	 * @param string     $getter         Getter method name.
	 * @param null|array $mandatory_data Mandatory data required to prevent validation issues.
	 */
	protected function assertOptionalField( string $getter, array $mandatory_data = null ): void {
		$class          = $this->get_schema_class();
		$mandatory_data = $mandatory_data ?? $this->mandatory_data();
		$instance       = $class::from_array( $mandatory_data );

		$this->assertNull( $instance->$getter() );
		$this->assertEmpty( $instance->validate() );
	}

	/**
	 * Tests that a boolean field returns the expected default state when missing.
	 *
	 * @param string $getter        Getter method name.
	 * @param bool   $default_state Expected default value (default: false).
	 * @param array  $extra_data    Additional data required for validation.
	 */
	protected function assertBooleanFieldDefaultState( string $getter, bool $default_state = false, array $extra_data = array() ): void {
		$class    = $this->get_schema_class();
		$instance = $class::from_array( $extra_data );

		$this->assertEquals( $default_state, $instance->$getter() );
	}

	/**
	 * Tests string field max length validation.
	 *
	 * @param string     $field_name     Field name in the data array (supports dot notation).
	 * @param int        $max_length     Maximum allowed length.
	 * @param array|null $mandatory_data Additional data required for validation.
	 */
	protected function assertStringFieldMaxLength( string $field_name, int $max_length, array $mandatory_data = null ): void {
		$class          = $this->get_schema_class();
		$mandatory_data = $mandatory_data ?? $this->mandatory_data();

		// Test exceeding max length produces validation issue
		$too_long = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, str_repeat( 'a', $max_length + 1 ) ) );
		$instance = $class::from_array( $too_long );
		$issues   = $instance->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when exceeding $max_length characters" );

		$issue_fields = array_map(
			static fn( $issue ) => $issue->to_array()['field'],
			$issues
		);
		$this->assertContains( $field_name, $issue_fields, "Expected validation error for invalid length of '$field_name'" );

		// Test at max length is valid
		$at_max   = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, str_repeat( 'a', $max_length ) ) );
		$instance = $class::from_array( $at_max );
		$issues   = $instance->validate();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at exactly $max_length characters" );
	}

	/**
	 * Tests that a field returns the expected type (object instance or primitive type).
	 *
	 * @param array  $input_data    Data to set on the schema (e.g., ['phone' => [...]] ).
	 * @param string $getter        Getter method name to test.
	 * @param string $expected_type Expected class name or primitive type (e.g., Money::class,
	 *                              'array', 'string').
	 * @param array  $extra_data    Additional data required for validation (e.g., required
	 *                              fields).
	 */
	protected function assertFieldReturnsType( array $input_data, string $getter, string $expected_type, array $extra_data = array() ): void {
		$class    = $this->get_schema_class();
		$data     = array_merge( $extra_data, $input_data );
		$instance = $class::from_array( $data );

		$value = $instance->$getter();

		if ( class_exists( $expected_type ) ) {
			$this->assertInstanceOf( $expected_type, $value, "Getter '$getter()' should return instance of $expected_type" );
		} else {
			$this->assertSame( $expected_type, gettype( $value ), "Getter '$getter()' should return type $expected_type" );
		}
	}

	/**
	 * Tests that empty strings are preserved (not converted to null).
	 *
	 * @param string      $field_name Field name in the data array (supports dot notation).
	 * @param string|null $getter     Getter method name (supports dot notation).
	 * @param array       $extra_data Additional data required for validation.
	 */
	protected function assertEmptyStringPreserved( string $field_name, string $getter = null, array $extra_data = array() ): void {
		$getter   = $getter ?? $field_name;
		$class    = $this->get_schema_class();
		$data     = array_merge( $extra_data, $this->setNestedValue( array(), $field_name, '' ) );
		$instance = $class::from_array( $data );

		$actual = $this->getNestedValue( $instance, $getter );
		$this->assertSame( '', $actual, "Field '{$field_name}' should be empty string'" );
	}

	/**
	 * Tests that whitespace is trimmed from string values.
	 *
	 * @param string      $field_name     Field name in the data array (supports dot notation).
	 * @param string      $input_value    Input value with whitespace.
	 * @param mixed       $expected_value Expected trimmed value.
	 * @param string|null $getter         Getter method name (supports dot notation).
	 * @param array|null  $mandatory_data Additional data required for validation.
	 */
	protected function assertWhitespaceTrimming( string $field_name, string $input_value, $expected_value = null, string $getter = null, array $mandatory_data = null ): void {
		$getter         = $getter ?? $field_name;
		$expected_value = $expected_value ?? $input_value;
		$mandatory_data = $mandatory_data ?? $this->mandatory_data();
		$class          = $this->get_schema_class();
		$data           = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, $input_value ) );
		$instance       = $class::from_array( $data );

		$actual = $this->getNestedValue( $instance, $getter );
		$this->assertEquals( $expected_value, $actual );
	}

}
