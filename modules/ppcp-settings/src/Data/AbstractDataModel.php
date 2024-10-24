<?php
/**
 * Abstract Data Model Base Class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;

/**
 * Abstract class AbstractDataModel
 *
 * Provides a base implementation for data models that can be serialized to and from arrays,
 * and provide persistence capabilities.
 */
abstract class AbstractDataModel {
	/**
	 * Stores the model data.
	 *
	 * @var array
	 */
	protected array $data = array();

	/**
	 * Option key for WordPress storage.
	 * Must be overridden by the child class!
	 */
	protected const OPTION_KEY = '';

	/**
	 * Default values for the model.
	 * Child classes should override this method to define their default structure.
	 *
	 * @return array
	 */
	abstract protected function get_defaults() : array;

	/**
	 * Constructor.
	 *
	 * @throws RuntimeException If the OPTION_KEY is not defined in the child class.
	 */
	public function __construct() {
		if ( empty( static::OPTION_KEY ) ) {
			throw new RuntimeException( 'OPTION_KEY must be defined in child class.' );
		}

		$this->data = $this->get_defaults();
		$this->load();
	}

	/**
	 * Loads the model data from WordPress options.
	 */
	public function load() : void {
		$saved_data = get_option( static::OPTION_KEY, array() );
		$this->data = array_merge( $this->data, $saved_data );
	}

	/**
	 * Saves the model data to WordPress options.
	 */
	public function save() : void {
		update_option( static::OPTION_KEY, $this->data );
	}

	/**
	 * Gets all model data as an array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		return array_merge( array(), $this->data );
	}

	/**
	 * Sets all model data from an array.
	 *
	 * @param array $data The model data.
	 */
	public function from_array( array $data ) : void {
		foreach ( $data as $key => $value ) {
			if ( ! array_key_exists( $key, $this->data ) ) {
				continue;
			}

			$setter = "set_$key";
			if ( method_exists( $this, $setter ) ) {
				$this->$setter( $value );
			} else {
				$this->data[ $key ] = $value;
			}
		}
	}
}
