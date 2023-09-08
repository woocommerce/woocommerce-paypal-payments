<?php
/**
 * The Singleton Trait can be used to add singleton behaviour to a class.
 *
 * @package WooCommerce\PayPalCommerce\Common\Pattern
 */

namespace WooCommerce\PayPalCommerce\Common\Pattern;

/**
 * Class SingletonTrait.
 */
trait SingletonTrait {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * Static method to get the instance of the Singleton class
	 *
	 * @return self|null
	 */
	public static function get_instance(): ?self {
		return self::$instance;
	}

	/**
	 * Static method to get the instance of the Singleton class
	 *
	 * @param self $instance
	 * @return self
	 */
	protected static function set_instance( self $instance ): self {
		self::$instance = $instance;
		return self::$instance;
	}

}
