<?php
/**
 * The Purchase Unit Repository interface.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

/**
 * Interface PurchaseUnitRepositoryInterface
 */
interface PurchaseUnitRepositoryInterface {


	/**
	 * Returns all purchase units.
	 *
	 * @return PurchaseUnit[]
	 */
	public function all(): array;
}
