<?php
/**
 * The capabilities of a seller status.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class SellerStatusCapability
 */
class SellerStatusCapability {

	const STATUS_ACTIVE = 'ACTIVE';

	/**
	 * The name of the product.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The status of the capability.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * SellerStatusCapability constructor.
	 *
	 * @param string $name   The name of the product.
	 * @param string $status The status of the capability.
	 */
	public function __construct(
		string $name,
		string $status
	) {
		$this->name   = $name;
		$this->status = $status;
	}

	/**
	 * Returns the name of the product.
	 *
	 * @return string
	 */
	public function name() : string {
		return $this->name;
	}

	/**
	 * Returns the status for this capability.
	 *
	 * @return string
	 */
	public function status() : string {
		return $this->status;
	}

	/**
	 * Returns the entity as array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		return array(
			'name'   => $this->name(),
			'status' => $this->status(),
		);
	}

}
