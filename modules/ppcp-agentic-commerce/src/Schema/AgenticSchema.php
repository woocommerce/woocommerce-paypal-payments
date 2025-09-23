<?php
/**
 * Base class for the agentic commerce schema classes.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors\AgenticError;

/**
 * Agentic schema classes must enforce immutability - only constructor can set properties!
 */
abstract class AgenticSchema {

	/**
	 * Returns a list of validation errors or an empty array when the object is valid.
	 */
	abstract public function validate(): array;

	/**
	 * Returns a key-value array that represents the object's internal state.
	 */
	abstract public function to_array(): array;

	/**
	 * Factory method to create a new object from the key-value array.
	 *
	 * @param array $data Key-value array.
	 * @return static|AgenticError New instance, or error details.
	 */
	abstract public static function from_array( array $data );

	/**
	 * Helper that creates a new instance with a specific change based on the current object.
	 *
	 * @param array $changes Key-value set of changes to apply.
	 * @return static|AgenticError New instance, or error details.
	 */
	public function with( array $changes ) {
		$current = $this->to_array();
		$merged  = array_merge( $current, $changes );

		return static::from_array( $merged );
	}
}
