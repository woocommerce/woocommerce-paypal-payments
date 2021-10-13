<?php
/**
 * The cart repository returns the purchase units from the current \WC_Cart.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

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
	 * Returns all Pur of the WooCommerce cart.
	 *
	 * @return PurchaseUnit[]
	 */
	public function all(): array {
		$cart = WC()->cart ?? new \WC_Cart();
		return array( $this->factory->from_wc_cart( $cart ) );
	}
}
