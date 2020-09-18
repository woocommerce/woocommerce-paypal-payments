<?php
/**
 * The seller status entity.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class SellerStatus
 */
class SellerStatus {

	/**
	 * The products.
	 *
	 * @var SellerStatusProduct[]
	 */
	private $products;

	/**
	 * SellerStatus constructor.
	 *
	 * @param SellerStatusProduct[] $products The products.
	 */
	public function __construct( array $products ) {
		foreach ( $products as $key => $product ) {
			if ( is_a( $product, SellerStatusProduct::class ) ) {
				continue;
			}
			unset( $products[ $key ] );
		}
		$this->products = $products;
	}

	/**
	 * Returns the products.
	 *
	 * @return SellerStatusProduct[]
	 */
	public function products() : array {
		return $this->products;
	}

	/**
	 * Returns the enitity as array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		$products = array_map(
			function( SellerStatusProduct $product ) : array {
				return $product->to_array();
			},
			$this->products()
		);

		return array(
			'products' => $products,
		);
	}
}
