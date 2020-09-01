<?php
/**
 * The cart repository returns the purchase units from the current \WC_Cart.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

/**
 * Class CartRepository
 */
class CartRepository implements PurchaseUnitRepositoryInterface {

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $factory;

	/**
	 * CartRepository constructor.
	 *
	 * @param PurchaseUnitFactory $factory The purchase unit factory.
	 */
	public function __construct( PurchaseUnitFactory $factory ) {
		$this->factory = $factory;
	}

	/**
	 * Returns all Pur of the Woocommerce cart.
	 *
	 * @return PurchaseUnit[]
	 */
	public function all(): array {
		$cart = WC()->cart ?? new \WC_Cart();
		return array( $this->factory->from_wc_cart( $cart ) );
	}
}
