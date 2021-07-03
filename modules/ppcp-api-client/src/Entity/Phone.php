<?php
/**
 * The Phone object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Phone
 */
class Phone {

	/**
	 * The number.
	 *
	 * @var string
	 */
	private $national_number;

	/**
	 * Phone constructor.
	 *
	 * @param string $national_number The number.
	 */
	public function __construct( string $national_number ) {
		$this->national_number = $national_number;
	}

	/**
	 * Returns the number.
	 *
	 * @return string
	 */
	public function national_number(): string {
		return $this->national_number;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'national_number' => $this->national_number(),
		);
	}
}
