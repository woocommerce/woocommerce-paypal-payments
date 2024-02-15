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
	 * The capabilities.
	 *
	 * @var SellerStatusCapability[]
	 */
	private $capabilities;

	/**
	 * SellerStatus constructor.
	 *
	 * @param SellerStatusProduct[]    $products The products.
	 * @param SellerStatusCapability[] $capabilities The capabilities.
	 *
	 * @psalm-suppress RedundantConditionGivenDocblockType
	 */
	public function __construct( array $products, array $capabilities ) {
		foreach ( $products as $key => $product ) {
			if ( is_a( $product, SellerStatusProduct::class ) ) {
				continue;
			}
			unset( $products[ $key ] );
		}
		foreach ( $capabilities as $key => $capability ) {
			if ( is_a( $capability, SellerStatusCapability::class ) ) {
				continue;
			}
			unset( $capabilities[ $key ] );
		}

		$this->products     = $products;
		$this->capabilities = $capabilities;
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
	 * Returns the capabilities.
	 *
	 * @return SellerStatusCapability[]
	 */
	public function capabilities() : array {
		return $this->capabilities;
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

		$capabilities = array_map(
			function( SellerStatusCapability $capability ) : array {
				return $capability->to_array();
			},
			$this->capabilities()
		);

		return array(
			'products'     => $products,
			'capabilities' => $capabilities,
		);
	}
}
