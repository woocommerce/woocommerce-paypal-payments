<?php
/**
 * The Purchase Unit Repository interface.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

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
