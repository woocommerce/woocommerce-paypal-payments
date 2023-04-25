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
	 * Shipping methods.
	 *
	 * @var ShippingOption[]
	 */
	private $options;

	/**
	 * Shipping constructor.
	 *
	 * @param string           $name The name.
	 * @param Address          $address The address.
	 * @param ShippingOption[] $options Shipping methods.
	 */
	public function __construct( string $name, Address $address, array $options = array() ) {
		$this->name    = $name;
		$this->address = $address;
		$this->options = $options;
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
	 * Returns the shipping methods.
	 *
	 * @return ShippingOption[]
	 */
	public function options(): array {
		return $this->options;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$result = array(
			'name'    => array(
				'full_name' => $this->name(),
			),
			'address' => $this->address()->to_array(),
		);
		if ( $this->options ) {
			$result['options'] = array_map(
				function ( ShippingOption $opt ): array {
					return $opt->to_array();
				},
				$this->options
			);
		}
		return $result;
	}
}
