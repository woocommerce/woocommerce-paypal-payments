<?php
/**
 * Abstract base class for testing AgenticSchema implementations.
 *
 * Provides common test cases and helpers for consistent schema validation testing.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
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
	 * A flat array defining the expected getter responses for an instance
	 * that was initialized using the `get_valid_data()` response.
	 *
	 * @return array Map of getter names to expected values (e.g., ['country_code' => 'US']).
	 */
	abstract protected function get_expected_data(): array;

	abstract protected function get_data_types(): array;

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
		$class      = $this->get_schema_class();
		$validation = new StoreValidation();
		$instance   = $class::from_array( $this->get_valid_data(), $validation );

		$this->assertInstanceOf( $class, $instance );
		$this->assertEmpty( $validation->all(), 'Valid data should not produce validation issues' );
	}

	/**
	 * Tests that valid data is correctly parsed and accessible via getters.
	 * Subclasses should override get_expected_values() to define field->getter mappings.
	 */
	public function test_valid_data_accessible_via_getters(): void {
		$expectations = $this->get_expected_data();

		if ( empty( $expectations ) ) {
			$this->markTestSkipped( 'No getter mappings defined - override get_expected_values() to test' );
		}

		$class      = $this->get_schema_class();
		$validation = new StoreValidation();
		$instance   = $class::from_array( $this->get_valid_data(), $validation );

		$this->assertEmpty( $validation->all(), 'Valid data should pass validation' );

		foreach ( $expectations as $field_name => $expected ) {
			$actual = $this->get_nested_value( $instance, $field_name );
			$this->assertSame( $expected, $actual, "Getter '$field_name()' should return '$expected' value" );
		}
	}

	/**
	 * Tests that all fields accept only their declared types.
	 *
	 * @see get_data_type
	 * @see assertFieldAcceptsOnlyType
	 */
	public function test_fields_accept_only_declared_types(): void {
		$data_types = $this->get_data_types();

		if ( empty( $data_types ) ) {
			// Skip, no plain data-types to verify.
			$this->addToAssertionCount( 1 );

			return;
		}

		foreach ( $data_types as $field_name => $type_config ) {
			$type    = is_array( $type_config ) ? $type_config['type'] : $type_config;
			$default = is_array( $type_config ) ? ( $type_config['default'] ?? null ) : null;
			$valid   = is_array( $type_config ) ? ( $type_config['valid'] ?? null ) : null;

			if ( ! is_null( $valid ) && ! is_array( $valid ) ) {
				$valid = array( $valid );
			}

			// Positive tests: field accepts valid values
			$this->assertFieldAcceptsValidTypes( $field_name, $type, $valid );

			// Negative tests: field rejects wrong types
			$this->assertFieldRejectsInvalidTypes( $field_name, $type, $default );
		}
	}

	/**
	 * ----------------------------------------------------------------------
	 * CUSTOM ASSERTIONS
	 *
	 * Assertion methods use camelCase to align with PHPUnit's built-in
	 * "assertSomething" convention.
	 * ----------------------------------------------------------------------
	 */

	/**
	 * Tests that a required field produces validation error when missing.
	 *
	 * @param string $field_name Expected validation error field key.
	 */
	protected function assertRequiredField( string $field_name ): void {
		$class      = $this->get_schema_class();
		$validation = new StoreValidation();
		$class::from_array( array(), $validation );
		$issues = $validation->all();

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
	 * @param string $field_name Getter method name.
	 */
	protected function assertOptionalField( string $field_name ): void {
		$class          = $this->get_schema_class();
		$mandatory_data = $this->mandatory_data();
		$validation     = new StoreValidation();
		$instance       = $class::from_array( $mandatory_data, $validation );

		$this->assertNull( $instance->$field_name() );
		$this->assertEmpty( $validation->all() );
	}

	/**
	 * Tests that a boolean field returns the expected default state when missing.
	 *
	 * @param string $field_name Getter method name.
	 */
	protected function assertBooleanFieldDefaultState( string $field_name ): void {
		$class    = $this->get_schema_class();
		$data     = array();
		$instance = $class::from_array( $data, new StoreValidation() );

		$this->assertSame( false, $instance->$field_name() );
	}

	/**
	 * Tests string field exact length validation.
	 *
	 * @param string $field_name   Field name in the data array (supports dot notation).
	 * @param int    $exact_length Required exact length.
	 */
	protected function assertStringFieldExactLength( string $field_name, int $exact_length ): void {
		$class          = $this->get_schema_class();
		$mandatory_data = $this->mandatory_data();

		// Test below exact length produces validation issue
		$too_short  = array_merge(
			$mandatory_data, $this->set_nested_value( array(), $field_name, str_repeat( 'a', $exact_length - 1 ) )
		);
		$validation = new StoreValidation();
		$class::from_array( $too_short, $validation );
		$issues = $validation->all();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when below $exact_length characters" );

		$issue_fields = array_map(
			static fn( $issue ) => $issue->to_array()['field'],
			$issues
		);
		$this->assertContains( $field_name, $issue_fields, "Expected validation error for invalid length of '$field_name'" );

		// Test at exact length is valid
		$at_exact   = array_merge(
			$mandatory_data, $this->set_nested_value( array(), $field_name, str_repeat( 'a', $exact_length ) )
		);
		$validation = new StoreValidation();
		$class::from_array( $at_exact, $validation );
		$issues = $validation->all();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at exactly $exact_length characters" );

		// Test above exact length produces validation issue
		$too_long   = array_merge(
			$mandatory_data, $this->set_nested_value( array(), $field_name, str_repeat( 'a', $exact_length + 1 ) )
		);
		$validation = new StoreValidation();
		$class::from_array( $too_long, $validation );
		$issues = $validation->all();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when above $exact_length characters" );
		$this->assertContains( $field_name, $issue_fields, "Expected validation error for invalid length of '$field_name'" );
	}

	/**
	 * Tests string field max length validation.
	 *
	 * @param string $field_name Field name in the data array (supports dot notation).
	 * @param int    $max_length Maximum allowed length.
	 */
	protected function assertStringFieldMaxLength( string $field_name, int $max_length ): void {
		$class          = $this->get_schema_class();
		$mandatory_data = $this->mandatory_data();

		// Test exceeding max length produces validation issue
		$too_long   = array_merge(
			$mandatory_data, $this->set_nested_value( array(), $field_name, str_repeat( 'a', $max_length + 1 ) )
		);
		$validation = new StoreValidation();
		$class::from_array( $too_long, $validation );
		$issues = $validation->all();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when exceeding $max_length characters" );

		$issue_fields = array_map(
			static fn( $issue ) => $issue->to_array()['field'],
			$issues
		);
		$this->assertContains( $field_name, $issue_fields, "Expected validation error for invalid length of '$field_name'" );

		// Test at max length is valid
		$at_max     = array_merge(
			$mandatory_data, $this->set_nested_value( array(), $field_name, str_repeat( 'a', $max_length ) )
		);
		$validation = new StoreValidation();
		$class::from_array( $at_max, $validation );
		$issues = $validation->all();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at exactly $max_length characters" );
	}

	/**
	 * Tests integer field min/max range validation.
	 *
	 * @param string $field_name Field name in the data array.
	 * @param int    $min        Minimum allowed value.
	 * @param int    $max        Maximum allowed value.
	 */
	protected function assertIntegerFieldRange( string $field_name, int $min, int $max ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		// Test below minimum
		$below_min  = array_merge( $mandatory_data, array( $field_name => $min - 1 ) );
		$validation = new StoreValidation();
		$class::from_array( $below_min, $validation );
		$issues = $validation->all();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when below $min" );
		$this->assertSame( $field_name, $issues[0]->to_array()['field'] );

		// Test at minimum (valid)
		$at_min     = array_merge( $mandatory_data, array( $field_name => $min ) );
		$validation = new StoreValidation();
		$class::from_array( $at_min, $validation );
		$issues = $validation->all();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at minimum value $min" );

		// Test above maximum
		$above_max  = array_merge( $mandatory_data, array( $field_name => $max + 1 ) );
		$validation = new StoreValidation();
		$class::from_array( $above_max, $validation );
		$issues = $validation->all();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when above $max" );
		$this->assertSame( $field_name, $issues[0]->to_array()['field'] );

		// Test at maximum (valid)
		$at_max     = array_merge( $mandatory_data, array( $field_name => $max ) );
		$validation = new StoreValidation();
		$class::from_array( $at_max, $validation );
		$issues = $validation->all();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at maximum value $max" );
	}

	/**
	 * Tests array field min count validation.
	 *
	 * @param string $field_name    Field name in the data array.
	 * @param int    $min_count     Minimum allowed number of items.
	 * @param array  $item_template Template for generating array items.
	 */
	protected function assertArrayFieldMinCount( string $field_name, int $min_count, array $item_template ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		// Test below min count produces validation issue
		if ( $min_count > 0 ) {
			$too_few    = array();
			$data       = array_merge( $mandatory_data, array( $field_name => $too_few ) );
			$validation = new StoreValidation();
			$class::from_array( $data, $validation );
			$issues = $validation->all();

			$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation with less than $min_count items" );

			$issue_fields = array_map(
				static fn( $issue ) => $issue->to_array()['field'],
				$issues
			);
			$this->assertContains( $field_name, $issue_fields );
		}

		// Test at min count is valid
		$at_min = array();
		for ( $i = 0; $i < $min_count; $i ++ ) {
			$at_min[] = $item_template;
		}

		$data       = array_merge( $mandatory_data, array( $field_name => $at_min ) );
		$validation = new StoreValidation();
		$class::from_array( $data, $validation );
		$issues = $validation->all();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid with exactly $min_count items" );
	}

	/**
	 * Tests array field max count validation.
	 *
	 * @param string $field_name    Field name in the data array.
	 * @param int    $max_count     Maximum allowed number of items.
	 * @param array  $item_template Template for generating array items.
	 */
	protected function assertArrayFieldMaxCount( string $field_name, int $max_count, array $item_template ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		// Test exceeding max count
		$too_many = array();
		for ( $i = 0; $i < $max_count + 1; $i ++ ) {
			$item = $item_template;
			// Replace any {index} placeholders in the template
			array_walk_recursive(
				$item,
				function ( &$value ) use ( $i ) {
					if ( is_string( $value ) ) {
						$value = str_replace( '{index}', (string) $i, $value );
					}
				}
			);
			$too_many[] = $item;
		}

		$data       = array_merge( $mandatory_data, array( $field_name => $too_many ) );
		$validation = new StoreValidation();
		$class::from_array( $data, $validation );
		$issues = $validation->all();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when exceeding $max_count items" );
		$this->assertSame( $field_name, $issues[0]->to_array()['field'] );

		// Test at max count (valid)
		$at_max     = array_slice( $too_many, 0, $max_count );
		$data       = array_merge( $mandatory_data, array( $field_name => $at_max ) );
		$validation = new StoreValidation();
		$class::from_array( $data, $validation );
		$issues = $validation->all();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid with exactly $max_count items" );
	}

	/**
	 * Tests that a field accepts all valid values for its declared type.
	 *
	 * @param string     $field_name    Field name in the data array (supports dot notation).
	 * @param string     $expected_type Expected type (e.g., 'string', 'int', 'country', 'email').
	 * @param array|null $valid_values  Optional override for valid test values.
	 */
	protected function assertFieldAcceptsValidTypes( string $field_name, string $expected_type, array $valid_values = null ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		// Valid values that should be accepted per type
		$known_types = array(
			'country'   => array( 'US' ),
			'currency'  => array( 'USD' ),
			'string'    => array( 'valid' ),
			'int'       => array( 42 ),
			'float'     => array( 3.14 ),
			'number'    => array( '25.00', 25, 25.0 ),
			'date'      => array( '2024-12-25' ),
			'timestamp' => array( '2024-12-25T09:00:00Z' ),
			'email'     => array( 'test@example.com' ),
			'bool'      => array( true, false ),
			'array'     => array( array( 'key' => 'value' ), array() ),
		);

		if ( ! isset( $known_types[ $expected_type ] ) ) {
			$this->fail( "Unknown type '$expected_type'. Valid types: " . implode( ', ', array_keys( $known_types ) ) );
		}

		$test_values = $valid_values ?? $known_types[ $expected_type ];

		foreach ( $test_values as $input_value ) {
			$data     = array_merge(
				$mandatory_data, $this->set_nested_value( array(), $field_name, $input_value )
			);
			$instance = $class::from_array( $data, new StoreValidation() );
			$actual   = $this->get_nested_value( $instance, $field_name );

			// Handle case normalization for string types
			if ( 'string' === $expected_type ) {
				$actual = strtolower( $actual );
			}

			$this->assertEquals(
				$input_value,
				$actual,
				"Field '$field_name' should accept valid $expected_type value: " . var_export( $input_value, true )
			);
		}
	}

	/**
	 * Tests that a field rejects incompatible types and returns the specified default.
	 *
	 * @param string $field_name    Field name in the data array (supports dot notation).
	 * @param string $expected_type Expected type that should be accepted.
	 * @param mixed  $default_value Expected value when type is incompatible.
	 */
	protected function assertFieldRejectsInvalidTypes( string $field_name, string $expected_type, $default_value ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		// Primitive types to test for rejection
		$primitive_values = array(
			'string' => 'a',
			'int'    => 42,
			'float'  => 3.14,
			'true'   => true,
			'false'  => false,
			'array'  => array(),
			'null'   => null,
		);

		// Which primitive types are compatible with each semantic type
		$compatible_primitives = array(
			'number'    => array( 'int', 'float', 'string' ),
			'timestamp' => array( 'string' ),
			'date'      => array( 'string' ),
			'email'     => array( 'string' ),
			'country'   => array( 'string' ),
			'currency'  => array( 'string' ),
			'string'    => array( 'string' ),
			'int'       => array( 'int' ),
			'float'     => array( 'float' ),
			'bool'      => array( 'true', 'false' ),
		);

		$allowed_primitives = $compatible_primitives[ $expected_type ] ?? array( $expected_type );

		foreach ( $primitive_values as $primitive_type => $input_value ) {
			// Skip compatible types
			if ( in_array( $primitive_type, $allowed_primitives, true ) ) {
				continue;
			}

			$data     = array_merge(
				$mandatory_data, $this->set_nested_value( array(), $field_name, $input_value )
			);
			$instance = $class::from_array( $data, new StoreValidation() );
			$actual   = $this->get_nested_value( $instance, $field_name );

			$this->assertSame(
				$default_value,
				$actual,
				"Field '$field_name' (type: $expected_type) should reject primitive type '$primitive_type' and return default"
			);
		}
	}

	/**
	 * Tests that empty strings are preserved (not converted to null).
	 *
	 * @param string $field_name Field name in the data array (supports dot notation).
	 */
	protected function assertEmptyStringPreserved( string $field_name ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();
		$data           = array_merge(
			$mandatory_data, $this->set_nested_value( array(), $field_name, '' )
		);
		$instance       = $class::from_array( $data, new StoreValidation() );

		$actual = $this->get_nested_value( $instance, $field_name );
		$this->assertSame( '', $actual, "Field '{$field_name}' should be empty string'" );
	}

	/**
	 * Tests that whitespace is trimmed from string values.
	 *
	 * @param string $field_name  Field name in the data array (supports dot notation).
	 * @param mixed  $clean_value The expected clean value (without whitespace).
	 */
	protected function assertWhitespaceTrimming( string $field_name, $clean_value ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		$test_cases = $this->get_whitespace_trim_test_cases( $clean_value );

		foreach ( $test_cases as $description => list( $input_value, $expected_value ) ) {
			$data     = array_merge(
				$mandatory_data, $this->set_nested_value( array(), $field_name, $input_value )
			);
			$instance = $class::from_array( $data, new StoreValidation() );
			$actual   = $this->get_nested_value( $instance, $field_name );

			$this->assertEquals( $expected_value, $actual, "Failed whitespace trimming for case: $description" );
		}
	}

	/**
	 * Tests field format validation with multiple test cases.
	 *
	 * @param string $field_name         Field name in the data array (supports dot notation).
	 * @param array  $test_cases         Test cases [description => [input, is_valid,
	 *                                   expected_output]]. expected_output is optional -
	 *                                   defaults to input if not provided.
	 * @param mixed  $default_value      Expected value when validation fails (e.g., '', null).
	 */
	protected function assertFieldFormat( string $field_name, array $test_cases, $default_value = null ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		foreach ( $test_cases as $description => $case ) {
			$input           = $case[0];
			$is_valid        = $case[1];
			$expected_output = $case[2] ?? $input;

			$data       = array_merge( array(), $mandatory_data );
			$data       = $this->set_nested_value( $data, $field_name, $input );
			$validation = new StoreValidation();
			$instance   = $class::from_array( $data, $validation );
			$issues     = $validation->all();
			$actual     = $this->get_nested_value( $instance, $field_name );

			if ( $is_valid ) {
				$this->assertEmpty( $issues, "Case '$description': Expected no validation issues" );
				$this->assertSame( $expected_output, $actual, "Case '$description': Unexpected value from getter" );
			} else {
				$this->assertNotEmpty( $issues, "Case '$description': Expected validation issues" );
				$issue_fields = array_map(
					static fn( $issue ) => $issue->to_array()['field'],
					$issues
				);
				$this->assertContains( $field_name, $issue_fields, "Case '$description': Expected validation error for field '$field_name'" );
				$this->assertSame( $default_value, $actual, "Case '$description': Unexpected default value for invalid input" );
			}
		}
	}

	/**
	 * Tests that a field normalizes input to uppercase.
	 *
	 * @param string $field_name     Field name in the data array (supports dot notation).
	 * @param string $test_value     Base value to test (e.g., 'us' for country codes).
	 * @param string $expected_value Expected normalized value (e.g., 'US').
	 */
	protected function assertFieldNormalizesToUppercase( string $field_name, string $test_value, string $expected_value ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		$test_cases = array(
			'lowercase' => strtolower( $test_value ),
			'uppercase' => strtoupper( $test_value ),
			'mixed'     => ucfirst( strtolower( $test_value ) ),
		);

		foreach ( $test_cases as $case_type => $input ) {
			$data     = array_merge(
				$mandatory_data, $this->set_nested_value( array(), $field_name, $input )
			);
			$instance = $class::from_array( $data, new StoreValidation() );
			$actual   = $this->get_nested_value( $instance, $field_name );

			$this->assertSame(
				$expected_value,
				$actual,
				"Field '$field_name' should normalize $case_type input to uppercase"
			);
		}
	}

	/**
	 * Tests that a field preserves the exact case of input (case-sensitive).
	 *
	 * @param string $field_name Field name in the data array (supports dot notation).
	 * @param string $test_value Value with mixed case to test (e.g., 'JohnSmith').
	 */
	protected function assertFieldIsCaseSensitive( string $field_name, string $test_value ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		$test_cases = array(
			'lowercase' => strtolower( $test_value ),
			'uppercase' => strtoupper( $test_value ),
			'mixed'     => $test_value,
		);

		foreach ( $test_cases as $case_type => $input ) {
			$data     = array_merge(
				$mandatory_data, $this->set_nested_value( array(), $field_name, $input )
			);
			$instance = $class::from_array( $data, new StoreValidation() );
			$actual   = $this->get_nested_value( $instance, $field_name );

			$this->assertSame(
				$input,
				$actual,
				"Field '$field_name' should preserve exact case for $case_type input"
			);
		}
	}

	/**
	 * Tests that a field accepts special characters without modification.
	 *
	 * @param string     $field_name    Field name in the data array (supports dot notation).
	 * @param array|null $special_chars Characters to test (null = common set).
	 */
	protected function assertFieldAcceptsSpecialCharacters( string $field_name, array $special_chars = null ): void {
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		$default_chars = array(
			'hyphen'      => 'Test-Value',
			'underscore'  => 'Test_Value',
			'period'      => 'Test.Value',
			'comma'       => 'Test, Value',
			'apostrophe'  => "Test's Value",
			'parentheses' => 'Test (Value)',
			'ampersand'   => 'Test & Value',
			'exclamation' => 'Test! Value',
			'question'    => 'Test? Value',
			'colon'       => 'Test: Value',
			'slash'       => 'Test/Value',
			'plus'        => 'Test+Value',
			'equals'      => 'Test=Value',
			'at'          => 'Test@Value',
			'hash'        => 'Test#Value',
			'dollar'      => 'Test$Value',
			'percent'     => 'Test%Value',
			'unicode'     => 'Tëst Vãlüe',
			'emoji'       => 'Test 🎉 Value',
		);

		$test_cases = $special_chars ?? $default_chars;

		foreach ( $test_cases as $char_type => $input ) {
			$data     = array_merge(
				$mandatory_data, $this->set_nested_value( array(), $field_name, $input )
			);
			$instance = $class::from_array( $data, new StoreValidation() );
			$actual   = $this->get_nested_value( $instance, $field_name );

			$this->assertSame(
				$input,
				$actual,
				"Field '$field_name' should accept special character: $char_type"
			);
		}
	}

	/**
	 * ----------------------------------------------------------------------
	 * HELPERS - DATA ACCESS AND VALUE GENERATORS
	 * ----------------------------------------------------------------------
	 */

	/**
	 * Sets a nested value in an array using dot notation.
	 *
	 * @param array  $data  Array to modify.
	 * @param string $path  Dot-separated path (e.g., 'phone.country_code').
	 * @param mixed  $value Value to set.
	 * @return array Modified array.
	 */
	protected function set_nested_value( array $data, string $path, $value ): array {
		$keys = explode( '.', $path );
		$temp = &$data;

		foreach ( $keys as $key ) {
			if ( ! isset( $temp[ $key ] ) || ! is_array( $temp[ $key ] ) ) {
				$temp[ $key ] = array();
			}
			$temp = &$temp[ $key ];
		}

		$temp = $value;

		return $data;
	}

	/**
	 * Gets a nested value from an object using dot notation.
	 *
	 * @param object $instance Schema instance.
	 * @param string $path     Dot-separated path (e.g., 'phone.country_code').
	 * @return mixed Retrieved value.
	 */
	protected function get_nested_value( $instance, string $path ) {
		$keys  = explode( '.', $path );
		$value = $instance;

		foreach ( $keys as $key ) {
			if ( is_array( $value ) ) {
				$value = $value[ $key ] ?? null;
			} else {
				$value = $value->$key();
			}
		}

		return $value;
	}

	// === Common data providers ===

	/**
	 * Provides whitespace trimming test cases for a given clean value.
	 *
	 * @param mixed $clean_value Clean value without whitespace.
	 * @return array Test cases [description => [input_value, expected_value]].
	 */
	protected function get_whitespace_trim_test_cases( $clean_value ): array {
		$string_value = (string) $clean_value;

		return array(
			'leading space'  => array( " $string_value", $clean_value ),
			'trailing space' => array( "$string_value ", $clean_value ),
			'both spaces'    => array( "  $string_value  ", $clean_value ),
		);
	}

	/**
	 * Provides test cases for 2-letter country codes (ISO 3166-1 alpha-2).
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_country_code_format_cases(): array {
		return array(
			'United States'  => array( 'US', true ),
			'Canada'         => array( 'CA', true ),
			'United Kingdom' => array( 'GB', true ),
			'Germany'        => array( 'DE', true ),
			'lowercase us'   => array( 'us', true, 'US' ),
			'lowercase de'   => array( 'de', true, 'DE' ),
			'mixed case'     => array( 'Us', true, 'US' ),
			'single char'    => array( 'U', false ),
			'three chars'    => array( 'USA', false ),
			'with numbers'   => array( 'U1', false ),
			'with special'   => array( 'U-', false ),
			'empty'          => array( '', false ),
			'spaces'         => array( '  ', false ),
		);
	}

	/**
	 * Provides test cases for 3-letter currency codes (ISO 4217).
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_currency_code_format_cases(): array {
		return array(
			'US Dollar'     => array( 'USD', true ),
			'Euro'          => array( 'EUR', true ),
			'British Pound' => array( 'GBP', true ),
			'lowercase usd' => array( 'usd', true, 'USD' ),
			'mixed case'    => array( 'Usd', true, 'USD' ),
			'two chars'     => array( 'US', false ),
			'four chars'    => array( 'USDD', false ),
			'with numbers'  => array( 'US1', false ),
			'with special'  => array( 'US-', false ),
			'empty'         => array( '', false ),
		);
	}

	/**
	 * Provides test cases for phone country codes (1-3 digits).
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_phone_country_code_format_cases(): array {
		return array(
			'US single'          => array( '1', true ),
			'UK double'          => array( '44', true ),
			'Germany triple'     => array( '49', true ),
			'max length'         => array( '123', true ),
			'with leading space' => array( ' 1', true, '1' ),
			'empty'              => array( '', false ),
			'too long'           => array( '1234', false ),
			'with letters'       => array( '1a', false ),
			'with special'       => array( '1-', false ),
			'zero'               => array( '0', false ),
		);
	}

	/**
	 * Provides test cases for phone national numbers (1-14 digits).
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_phone_national_number_format_cases(): array {
		return array(
			'short'               => array( '123', true ),
			'medium'              => array( '5551234', true ),
			'long'                => array( '5551234567', true ),
			'max length'          => array( '12345678901234', true ),
			'with leading space'  => array( ' 555123', true, '555123' ),
			'with trailing space' => array( '555123 ', true, '555123' ),
			'empty'               => array( '', false ),
			'too long'            => array( '123456789012345', false ),
			'with letters'        => array( '555123a', false ),
			'with dashes'         => array( '555-1234', false ),
			'with parentheses'    => array( '(555)1234', false ),
		);
	}

	/**
	 * Provides test cases for email addresses (RFC 5322).
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_email_address_format_cases( bool $allow_empty = false ): array {
		return array(
			'simple'                    => array( 'test@example.com', true ),
			'with plus'                 => array( 'user+tag@example.com', true ),
			'with subdomain'            => array( 'user@mail.example.com', true ),
			'with dots'                 => array( 'first.last@example.com', true ),
			'with numbers'              => array( 'user123@example.com', true ),
			'with hyphens'              => array( 'user-name@ex-ample.com', true ),
			'with leading space'        => array( ' test@example.com', true, 'test@example.com' ),
			'with trailing space'       => array( 'test@example.com ', true, 'test@example.com' ),
			'short domain'              => array( 'a@b.co', true ),
			'max length valid'          => array(
				str_repeat( 'a', 64 ) . '@' . str_repeat( 'b', 63 ) . '.' . str_repeat( 'c', 63 ) . '.' . str_repeat( 'd', 61 ),
				true,
			),
			'no @'                      => array( 'notanemail', false ),
			'no domain'                 => array( 'user@', false ),
			'no local part'             => array( '@example.com', false ),
			'spaces'                    => array( 'user @example.com', false ),
			'invalid - exceeds max'     => array( str_repeat( 'a', 65 ) . '@example.com', false ),
			'invalid - domain too long' => array( 'user@' . str_repeat( 'a', 64 ) . '.com', false ),
			'empty'                     => array( '', $allow_empty ),
		);
	}

	/**
	 * Provides test cases for ISO 8601 / RFC 3339 datetime strings.
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_iso_date_format_cases( bool $allow_empty = false ): array {
		return array(
			'UTC'                => array( '2024-12-25T09:00:00Z', true ),
			'with offset'        => array( '2024-12-25T09:00:00+01:00', true ),
			'negative offset'    => array( '2024-12-25T09:00:00-05:00', true ),
			'with leading space' => array( ' 2024-12-25T09:00:00Z', true, '2024-12-25T09:00:00Z' ),
			'with milliseconds'  => array( '2024-12-25T09:00:00.123Z', false ),
			'date only'          => array( '2024-12-25', false ),
			'missing timezone'   => array( '2024-12-25T09:00:00', false ),
			'wrong format'       => array( '12/25/2024', false ),
			'missing seconds'    => array( '2024-12-25T09:00Z', false ),
			'empty'              => array( '', $allow_empty ),
		);
	}

	/**
	 * Provides test cases for YYYY-MM-DD date format.
	 *
	 * @see assertFieldFormat
	 * @return array Test cases [description => [input, is_valid, expected_output]].
	 */
	protected function get_ymd_date_format_cases( bool $allow_empty = false ): array {
		return array(
			'standard date'       => array( '2024-12-25', true ),
			'start of year'       => array( '2024-01-01', true ),
			'end of year'         => array( '2024-12-31', true ),
			'leap year Feb 29'    => array( '2024-02-29', true ),
			'end of month'        => array( '2024-11-30', true ),
			'with leading space'  => array( ' 2024-12-25', true, '2024-12-25' ),
			'with trailing space' => array( '2024-12-25 ', true, '2024-12-25' ),
			'with both spaces'    => array( ' 2024-12-25 ', true, '2024-12-25' ),
			'non-leap Feb 29'     => array( '2023-02-29', false ),
			'invalid month 13'    => array( '2024-13-01', false ),
			'invalid month 00'    => array( '2024-00-01', false ),
			'invalid day 32'      => array( '2024-01-32', false ),
			'invalid day 00'      => array( '2024-01-00', false ),
			'Feb 31'              => array( '2024-02-31', false ),
			'April 31'            => array( '2024-04-31', false ),
			'US format'           => array( '12/25/2024', false ),
			'EU format'           => array( '25-12-2024', false ),
			'short year'          => array( '24-12-25', false ),
			'missing day'         => array( '2024-12', false ),
			'missing month'       => array( '2024--25', false ),
			'with time'           => array( '2024-12-25T09:00:00', false ),
			'with timezone'       => array( '2024-12-25Z', false ),
			'text date'           => array( 'December 25, 2024', false ),
			'only spaces'         => array( '   ', $allow_empty ),
			'empty'               => array( '', $allow_empty ),
		);
	}

	protected function get_geo_latitude_test_cases(): array {
		return array(
			'zero'                 => array( '0', true, 0. ),
			'positive integer'     => array( '45', true, 45. ),
			'negative integer'     => array( '-45', true, - 45. ),
			'positive decimal'     => array( '37.7749', true, 37.7749 ),
			'negative decimal'     => array( '-33.8688', true, - 33.8688 ),
			'max positive'         => array( '90', true, 90. ),
			'leading space'        => array( '  90', true, 90. ),
			'trailing space'       => array( '90  ', true, 90. ),
			'max positive decimal' => array( '90.0', true, 90.0 ),
			'max negative'         => array( '-90', true, - 90. ),
			'max negative decimal' => array( '-90.0', true, - 90.0 ),
			'small positive'       => array( '0.0001', true, 0.0001 ),
			'small negative'       => array( '-0.0001', true, - 0.0001 ),
			'single digit'         => array( '5', true, 5. ),
			'89.9999'              => array( '89.9999', true, 89.9999 ),
			'int'                  => array( 23, true, 23. ),
			'float'                => array( 23.0, true, 23. ),
			'exceeds max'          => array( '90.1', false ),
			'exceeds min'          => array( '-90.1', false ),
			'way too large'        => array( '180', false ),
			'way too small'        => array( '-180', false ),
			'non-numeric'          => array( 'abc', false ),
			'with units'           => array( '45°', false ),
			'with comma'           => array( '45,5', false ),
			'multiple decimals'    => array( '45.5.5', false ),
		);
	}

	protected function get_geo_longitude_test_cases(): array {
		return array(
			'zero'                 => array( '0', true, 0. ),
			'positive integer'     => array( '90', true, 90. ),
			'negative integer'     => array( '-90', true, - 90. ),
			'positive decimal'     => array( '122.4194', true, 122.4194 ),
			'negative decimal'     => array( '-122.4194', true, - 122.4194 ),
			'max positive'         => array( '180', true, 180. ),
			'leading space'        => array( '  180', true, 180. ),
			'trailing space'       => array( '180  ', true, 180. ),
			'max positive decimal' => array( '180.0', true, 180.0 ),
			'max negative'         => array( '-180', true, - 180. ),
			'max negative decimal' => array( '-180.0', true, - 180.0 ),
			'small positive'       => array( '0.0001', true, 0.0001 ),
			'small negative'       => array( '-0.0001', true, - 0.0001 ),
			'single digit'         => array( '5', true, 5. ),
			'179.9999'             => array( '179.9999', true, 179.9999 ),
			'int'                  => array( 23, true, 23. ),
			'float'                => array( 23.0, true, 23. ),
			'exceeds max'          => array( '180.1', false ),
			'exceeds min'          => array( '-180.1', false ),
			'way too large'        => array( '360', false ),
			'way too small'        => array( '-360', false ),
			'non-numeric'          => array( 'xyz', false ),
			'with units'           => array( '-122°', false ),
			'with comma'           => array( '122,4', false ),
			'multiple decimals'    => array( '122.41.94', false ),
		);
	}

	protected function get_geo_subdivision_test_cases(): array {
		return array(
			'US state CA'           => array( 'CA', true ),
			'lowercase CA'          => array( 'ca', true, 'CA' ),
			'US state NY'           => array( 'NY', true ),
			'Canada province ON'    => array( 'ON', true ),
			'UK region ENG'         => array( 'ENG', true ),
			'ENG with spaces'       => array( ' ENG ', true, 'ENG' ),
			'Germany Bavaria BY'    => array( 'BY', true ),
			'Australia NSW'         => array( 'NSW', true ),
			'with hyphen'           => array( 'AB-CD', true ),
			'with numbers'          => array( 'CA1', true ),
			'with multiple hyphens' => array( 'A-B-C', true ),
			'alphanumeric mix'      => array( 'A1B2C3', true ),
			'max length 10 chars'   => array( 'ABCDEFGHIJ', true ),
			'exceeds max length'    => array( 'ABCDEFGHIJK', false ),
			'with spaces'           => array( 'CA NY', false ),
			'with special chars'    => array( 'CA_NY', false ),
			'with dots'             => array( 'CA.NY', false ),
			'with slash'            => array( 'CA/NY', false ),
		);
	}

	protected function get_coupon_action_test_cases(): array {
		// Allowed values are APPLY and REMOVE.
		return array(
			'valid apply'  => array( 'APPLY', true ),
			'remove'       => array( 'REMOVE', true ),
			'apply lower'  => array( 'apply', true, 'APPLY' ),
			'remove mixed' => array( 'ReMoVe', true, 'REMOVE' ),
			'invalid'      => array( 'INVALID', false ),
			'empty'        => array( '', false ),
		);
	}

	protected function get_money_value_cases( bool $allow_zero = true, bool $allow_negative = true ): array {
		return array(
			// Positive.
			'positive integer'      => array( '25', true, 25.0 ),
			'positive decimal'      => array( '25.99', true, 25.99 ),
			'three decimal jpy'     => array( '25.500', true, 25.5 ),
			'three decimal jpy 2'   => array( '25.599', true, 25.599 ),
			'large amount'          => array( '999999.99', true, 999999.99 ),
			'int amount'            => array( 10, true, 10.0 ),
			'float amount'          => array( 10.5, true, 10.5 ),
			// Zero.
			'zero string'           => array( '0', $allow_zero, 0.0 ),
			'zero int'              => array( 0, $allow_zero, 0.0 ),
			'zero float'            => array( 0., $allow_zero, 0.0 ),
			// Negative.
			'minus one'             => array( - 1, $allow_negative, - 1. ),
			'negative value'        => array( '-10.50', $allow_negative, - 10.5 ),
			'large negative amount' => array( '-999999.99', $allow_negative, - 999999.99 ),
			// Invalid format.
			'non numeric'           => array( 'abc', false ),
			'too many decimals'     => array( '10.1234', false ),
			'invalid format'        => array( '10,50', false ),
			'empty string'          => array( '', false ),
		);
	}
}
