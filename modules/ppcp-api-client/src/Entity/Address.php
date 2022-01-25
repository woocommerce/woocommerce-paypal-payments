<?php
/**
 * The address object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Address
 */
class Address {

	/**
	 * The country code.
	 *
	 * @var string
	 */
	private $country_code;

	/**
	 * The 1st address line.
	 *
	 * @var string
	 */
	private $address_line_1;

	/**
	 * The 2nd address line.
	 *
	 * @var string
	 */
	private $address_line_2;

	/**
	 * The admin area 1.
	 *
	 * @var string
	 */
	private $admin_area_1;

	/**
	 * The admin area 2.
	 *
	 * @var string
	 */
	private $admin_area_2;

	/**
	 * The postal code.
	 *
	 * @var string
	 */
	private $postal_code;

	/**
	 * Address constructor.
	 *
	 * @param string $country_code The country code.
	 * @param string $address_line_1 The 1st address line.
	 * @param string $address_line_2 The 2nd address line.
	 * @param string $admin_area_1 The admin area 1.
	 * @param string $admin_area_2 The admin area 2.
	 * @param string $postal_code The postal code.
	 */
	public function __construct(
		string $country_code,
		string $address_line_1 = '',
		string $address_line_2 = '',
		string $admin_area_1 = '',
		string $admin_area_2 = '',
		string $postal_code = ''
	) {

		$this->country_code   = $country_code;
		$this->address_line_1 = $address_line_1;
		$this->address_line_2 = $address_line_2;
		$this->admin_area_1   = $admin_area_1;
		$this->admin_area_2   = $admin_area_2;
		$this->postal_code    = $postal_code;
	}

	/**
	 * Returns the country code.
	 *
	 * @return string
	 */
	public function country_code(): string {
		return $this->country_code;
	}

	/**
	 * Returns the 1st address line.
	 *
	 * @return string
	 */
	public function address_line_1(): string {
		return $this->address_line_1;
	}

	/**
	 * Returns the 2nd address line.
	 *
	 * @return string
	 */
	public function address_line_2(): string {
		return $this->address_line_2;
	}

	/**
	 * Returns the admin area 1.
	 *
	 * @return string
	 */
	public function admin_area_1(): string {
		return $this->admin_area_1;
	}

	/**
	 * Returns the admin area 2.
	 *
	 * @return string
	 */
	public function admin_area_2(): string {
		return $this->admin_area_2;
	}

	/**
	 * Returns the postal code.
	 *
	 * @return string
	 */
	public function postal_code(): string {
		return $this->postal_code;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array_filter(
			array(
				'country_code'   => $this->country_code(),
				'address_line_1' => $this->address_line_1(),
				'address_line_2' => $this->address_line_2(),
				'admin_area_1'   => $this->admin_area_1(),
				'admin_area_2'   => $this->admin_area_2(),
				'postal_code'    => $this->postal_code(),
			)
		);
	}
}
