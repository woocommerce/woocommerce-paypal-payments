<?php

namespace WooCommerce\PayPalCommerce\Vendor\Pattern;

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
