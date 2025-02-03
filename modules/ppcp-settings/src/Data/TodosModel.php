<?php
/**
 * PayPal Commerce Todos Model
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class TodosModel
 *
 * Handles the storage and retrieval of task completion states in WordPress options table.
 * Provides methods to get and update completion states with proper type casting.
 */
class TodosModel {
	/**
	 * WordPress option name for storing the completion states.
	 *
	 * @var string
	 */
	protected const OPTION_NAME = 'ppcp_todos';

	/**
	 * Retrieves the formatted completion states from WordPress options.
	 *
	 * Loads the raw completion states from wp_options table and formats them into a
	 * standardized array structure with proper type casting.
	 *
	 * @return array The formatted completion states array.
	 */
	public function get() : array {
		$completion_states = get_option( self::OPTION_NAME, array() );

		return array_map(
		/**
		 * Ensures the task completion states are boolean values.
		 *
		 * @param mixed $state value to sanitize, as stored in the DB.
		 */
			static fn( $state ) => (bool) $state,
			$completion_states
		);
	}

	/**
	 * Updates the completion states in WordPress options.
	 *
	 * Converts the provided data array and saves it to wp_options table.
	 * Throws an exception if update fails.
	 *
	 * @param array $states Array of task IDs and their completion states.
	 * @return void
	 * @throws RuntimeException When the completion states update fails.
	 */
	public function update( array $states ) : void {
		$completion_states = array_map(
			static function ( $state ) {
				return (bool) $state;
			},
			$states
		);

		$result = update_option( self::OPTION_NAME, $completion_states );

		if ( ! $result ) {
			throw new RuntimeException( 'Failed to update todo completion states' );
		}
	}
}
