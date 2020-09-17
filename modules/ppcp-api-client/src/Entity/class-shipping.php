<?php
/**
 * The Shipping object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Shipping
 */
class Shipping {

	/**
	 * The name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The address.
	 *
	 * @var Address
	 */
	private $address;

	/**
	 * Shipping constructor.
	 *
	 * @param string  $name The name.
	 * @param Address $address The address.
	 */
	public function __construct( string $name, Address $address ) {
		$this->name    = $name;
		$this->address = $address;
	}

	/**
	 * Returns the name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Returns the shipping address.
	 *
	 * @return Address
	 */
	public function address(): Address {
		return $this->address;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'name'    => array(
				'full_name' => $this->name(),
			),
			'address' => $this->address()->to_array(),
		);
	}
}
