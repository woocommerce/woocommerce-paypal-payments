<?php
/**
 * The Product object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Product
 */
class Product {

	/**
	 * Product ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Product description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Product constructor.
	 *
	 * @param string $id Product ID.
	 * @param string $name Product name.
	 * @param string $description Product description.
	 */
	public function __construct( string $id, string $name, string $description = '' ) {
		$this->id          = $id;
		$this->name        = $name;
		$this->description = $description;
	}

	/**
	 * Returns the product ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Returns the product name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Returns the product description.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'          => $this->id(),
			'name'        => $this->name(),
			'description' => $this->description(),
		);
	}
}
