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
	 * A flat array defining the expected getter responses for an instance
	 * that was initialized using the `get_valid_data()` response.
	 *
	 * @return array Map of getter names to expected values (e.g., ['country_code' => 'US']).
	 */
	abstract protected function get_expected_data(): array;

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

	/**
	 * Tests that valid data is correctly parsed and accessible via getters.
	 * Subclasses should override get_expected_values() to define field->getter mappings.
	 */
	public function test_valid_data_accessible_via_getters(): void {
		$expectations = $this->get_expected_data();

		if ( empty( $expectations ) ) {
			$this->markTestSkipped( 'No getter mappings defined - override get_expected_values() to test' );
		}

		$class    = $this->get_schema_class();
		$instance = $class::from_array( $this->get_valid_data() );

		$this->assertEmpty( $instance->validate(), 'Valid data should pass validation' );

		foreach ( $expectations as $getter => $expected ) {
			$actual = $this->getNestedValue( $instance, $getter );
			$this->assertSame( $expected, $actual, "Getter '$getter()' should return '$expected' value" );
		}
	}

	// === Helper methods for common test patterns ===

	/**
	 * Tests that a required field produces validation error when missing.
	 *
	 * @param string $field_name Expected validation error field key.
	 */
	protected function assertRequiredField( string $field_name ): void {
		$class    = $this->get_schema_class();
		$data     = array();
		$instance = $class::from_array( $data );
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
	 * @param string $getter Getter method name.
	 */
	protected function assertOptionalField( string $getter ): void {
		$class          = $this->get_schema_class();
		$mandatory_data = $this->mandatory_data();
		$instance       = $class::from_array( $mandatory_data );

		$this->assertNull( $instance->$getter() );
		$this->assertEmpty( $instance->validate() );
	}

	/**
	 * Tests that a boolean field returns the expected default state when missing.
	 *
	 * @param string $getter        Getter method name.
	 * @param bool   $default_state Expected default value (default: false).
	 */
	protected function assertBooleanFieldDefaultState( string $getter, bool $default_state = false ): void {
		$class    = $this->get_schema_class();
		$data     = array();
		$instance = $class::from_array( $data );

		$this->assertEquals( $default_state, $instance->$getter() );
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
		$too_short = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, str_repeat( 'a', $exact_length - 1 ) ) );
		$instance  = $class::from_array( $too_short );
		$issues    = $instance->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when below $exact_length characters" );

		$issue_fields = array_map(
			static fn( $issue ) => $issue->to_array()['field'],
			$issues
		);
		$this->assertContains( $field_name, $issue_fields, "Expected validation error for invalid length of '$field_name'" );

		// Test at exact length is valid
		$at_exact = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, str_repeat( 'a', $exact_length ) ) );
		$instance = $class::from_array( $at_exact );
		$issues   = $instance->validate();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at exactly $exact_length characters" );

		// Test above exact length produces validation issue
		$too_long = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, str_repeat( 'a', $exact_length + 1 ) ) );
		$instance = $class::from_array( $too_long );
		$issues   = $instance->validate();

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
	 * Tests integer field min/max range validation.
	 *
	 * @param string $field_name     Field name in the data array.
	 * @param int    $min            Minimum allowed value.
	 * @param int    $max            Maximum allowed value.
	 * @param string $validation_key Expected validation error field key.
	 * @param array  $extra_data     Additional data required for validation.
	 */
	protected function assertIntegerFieldRange( string $field_name, int $min, int $max, string $validation_key = null, array $extra_data = array() ): void {
		$validation_key = $validation_key ?? $field_name;
		$class          = $this->get_schema_class();

		// Test below minimum
		$below_min = array_merge( $extra_data, array( $field_name => $min - 1 ) );
		$instance  = $class::from_array( $below_min );
		$issues    = $instance->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when below $min" );
		$this->assertSame( $validation_key, $issues[0]->to_array()['field'] );

		// Test at minimum (valid)
		$at_min   = array_merge( $extra_data, array( $field_name => $min ) );
		$instance = $class::from_array( $at_min );
		$issues   = $instance->validate();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at minimum value $min" );

		// Test above maximum
		$above_max = array_merge( $extra_data, array( $field_name => $max + 1 ) );
		$instance  = $class::from_array( $above_max );
		$issues    = $instance->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when above $max" );
		$this->assertSame( $validation_key, $issues[0]->to_array()['field'] );

		// Test at maximum (valid)
		$at_max   = array_merge( $extra_data, array( $field_name => $max ) );
		$instance = $class::from_array( $at_max );
		$issues   = $instance->validate();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid at maximum value $max" );
	}

	protected function assertArrayFieldMinCount( string $field_name, int $min_count, array $item_template, string $validation_key = null ): void {
		$validation_key = $validation_key ?? $field_name;
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		// Test below min count produces validation issue
		if ( $min_count > 0 ) {
			$too_few  = array();
			$data     = array_merge( $mandatory_data, array( $field_name => $too_few ) );
			$instance = $class::from_array( $data );
			$issues   = $instance->validate();

			$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation with less than $min_count items" );

			$issue_fields = array_map(
				static fn( $issue ) => $issue->to_array()['field'],
				$issues
			);
			$this->assertContains( $validation_key, $issue_fields );
		}

		// Test at min count is valid
		$at_min = array();
		for ( $i = 0; $i < $min_count; $i ++ ) {
			$at_min[] = $item_template;
		}

		$data     = array_merge( $mandatory_data, array( $field_name => $at_min ) );
		$instance = $class::from_array( $data );
		$issues   = $instance->validate();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid with exactly $min_count items" );
	}

	/**
	 * Tests array field max count validation.
	 *
	 * @param string $field_name     Field name in the data array.
	 * @param int    $max_count      Maximum allowed number of items.
	 * @param array  $item_template  Template for generating array items.
	 * @param string $validation_key Expected validation error field key.
	 * @param array  $extra_data     Additional data required for validation.
	 */
	protected function assertArrayFieldMaxCount( string $field_name, int $max_count, array $item_template, string $validation_key = null ): void {
		$validation_key = $validation_key ?? $field_name;
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

		$data     = array_merge( $mandatory_data, array( $field_name => $too_many ) );
		$instance = $class::from_array( $data );
		$issues   = $instance->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Field '$field_name' should fail validation when exceeding $max_count items" );
		$this->assertSame( $validation_key, $issues[0]->to_array()['field'] );

		// Test at max count (valid)
		$at_max   = array_slice( $too_many, 0, $max_count );
		$data     = array_merge( $mandatory_data, array( $field_name => $at_max ) );
		$instance = $class::from_array( $data );
		$issues   = $instance->validate();

		$this->assertEmpty( $issues, "Field '$field_name' should be valid with exactly $max_count items" );
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
	 */
	protected function assertEmptyStringPreserved( string $field_name, string $getter = null ): void {
		$getter         = $getter ?? $field_name;
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();
		$data           = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, '' ) );
		$instance       = $class::from_array( $data );

		$actual = $this->getNestedValue( $instance, $getter );
		$this->assertSame( '', $actual, "Field '{$field_name}' should be empty string'" );
	}

	/**
	 * Tests that whitespace is trimmed from string values.
	 *
	 * @param string      $field_name  Field name in the data array (supports dot notation).
	 * @param mixed       $clean_value The expected clean value (without whitespace).
	 * @param string|null $getter      Getter method name (supports dot notation).
	 */
	protected function assertWhitespaceTrimming( string $field_name, $clean_value, string $getter = null ): void {
		$getter         = $getter ?? $field_name;
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		$test_cases = $this->getWhitespaceTrimTestCases( $clean_value );

		foreach ( $test_cases as $description => list( $input_value, $expected_value ) ) {
			$data     = array_merge( $mandatory_data, $this->setNestedValue( array(), $field_name, $input_value ) );
			$instance = $class::from_array( $data );
			$actual   = $this->getNestedValue( $instance, $getter );

			$this->assertEquals( $expected_value, $actual, "Failed whitespace trimming for case: $description" );
		}
	}

	/**
	 * Tests that multiple validation errors are returned together.
	 *
	 * @param array $invalid_data    Invalid data with multiple errors.
	 * @param array $expected_fields Expected field names in validation errors.
	 * @param int   $expected_count  Expected number of validation errors.
	 */
	protected function assertMultipleValidationErrors( array $invalid_data, array $expected_fields, int $expected_count = null ): void {
		$expected_count = $expected_count ?? count( $expected_fields );
		$class          = $this->get_schema_class();
		$instance       = $class::from_array( $invalid_data );
		$issues         = $instance->validate();

		$this->assertCount( $expected_count, $issues, 'Should return all validation errors at once' );

		$actual_fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		foreach ( $expected_fields as $field ) {
			$this->assertContains( $field, $actual_fields, "Expected validation error for field: $field" );
		}
	}

	/**
	 * Tests field format validation with multiple test cases.
	 *
	 * @param string      $field_name      Field name in the data array (supports dot notation).
	 * @param array       $test_cases      Test cases [description => [input, is_valid,
	 *                                     expected_output]]. expected_output is optional -
	 *                                     defaults to input if not provided.
	 * @param string|null $getter          Getter method name (supports dot notation, defaults to
	 *                                     field_name).
	 * @param mixed       $invalid_default Expected value when validation fails (e.g., '', null,
	 *                                     0).
	 */
	protected function assertFieldFormat( string $field_name, array $test_cases, string $getter = null, $invalid_default = null ): void {
		$getter         = $getter ?? $field_name;
		$mandatory_data = $this->mandatory_data();
		$class          = $this->get_schema_class();

		foreach ( $test_cases as $description => $case ) {
			$input           = $case[0];
			$is_valid        = $case[1];
			$expected_output = $case[2] ?? $input;

			$data     = array_merge( array(), $mandatory_data );
			$data     = $this->setNestedValue( $data, $field_name, $input );
			$instance = $class::from_array( $data );
			$issues   = $instance->validate();
			$actual   = $this->getNestedValue( $instance, $getter );

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
				$this->assertSame( $invalid_default, $actual, "Case '$description': Unexpected default value for invalid input" );
			}
		}
	}

	// === Helper utilities for nested field access ===

	/**
	 * Sets a nested value in an array using dot notation.
	 *
	 * @param array  $data  Array to modify.
	 * @param string $path  Dot-separated path (e.g., 'phone.country_code').
	 * @param mixed  $value Value to set.
	 * @return array Modified array.
	 */
	protected function setNestedValue( array $data, string $path, $value ): array {
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
	protected function getNestedValue( $instance, string $path ) {
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
	protected function getWhitespaceTrimTestCases( $clean_value ): array {
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
	protected function getCountryCodeFormatCases(): array {
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
	protected function getCurrencyCodeFormatCases(): array {
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
	protected function getPhoneCountryCodeFormatCases(): array {
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
	protected function getPhoneNationalNumberFormatCases(): array {
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
	protected function getEmailAddressFormatCases( bool $allow_empty = false ): array {
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
	protected function getIsoDateFormatCases( bool $allow_empty = false ): array {
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
	protected function getYmdDateFormatCases( bool $allow_empty = false ): array {
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
}
