<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button;

use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\Button\Helper\IsolatedCartSimulator;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;

abstract class SimulateCartTestCase extends TestCase {

	/** @var int[] */
	protected $postIds = [];

	/** @var IsolatedCartSimulator */
	protected $cart_simulator;

	public function setUp(): void {
		parent::setUp();

		$data_store           = \WC_Data_Store::load( 'product' );
		$cart_products        = new CartProductsHelper( $data_store );
		$this->cart_simulator = new IsolatedCartSimulator( $cart_products, new NullLogger() );

		WC()->cart->empty_cart();
	}

	public function tearDown(): void {
		WC()->cart->empty_cart();

		foreach ( $this->postIds as $id ) {
			wp_delete_post( $id, true );
		}

		parent::tearDown();
	}

	protected function createSimpleProduct( float $price ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test product ' . uniqid() );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) $price );
		$product->set_tax_status( 'taxable' );
		$product->set_virtual( true );
		$product->save();

		$this->postIds[] = $product->get_id();

		return $product;
	}

	/**
	 * @return array{parent: WC_Product_Variable, variation: WC_Product_Variation}
	 */
	protected function createVariableProduct( float $price ): array {
		// Use a custom (non-taxonomy) attribute to avoid needing registered taxonomies.
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'color' );
		$attribute->set_options( [ 'red', 'blue' ] );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new WC_Product_Variable();
		$parent->set_name( 'Test variable ' . uniqid() );
		$parent->set_status( 'publish' );
		$parent->set_attributes( [ $attribute ] );
		$parent->save();

		$this->postIds[] = $parent->get_id();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( (string) $price );
		$variation->set_attributes( [ 'color' => 'red' ] );
		$variation->set_status( 'publish' );
		$variation->save();

		$this->postIds[] = $variation->get_id();

		// Sync so data store can find the variation.
		\WC_Product_Variable::sync( $parent->get_id() );

		return [ 'parent' => $parent, 'variation' => $variation ];
	}

	/**
	 * Builds a product data array in the format expected by the simulator.
	 *
	 * @param \WC_Product $product The product.
	 * @param int         $quantity Quantity.
	 * @param array       $variations Variation attributes.
	 * @param mixed       $extra Extra plugin data.
	 * @return array
	 */
	protected function productData( \WC_Product $product, int $quantity = 1, array $variations = [], $extra = null ): array {
		return [
			'product'    => $product,
			'quantity'   => $quantity,
			'variations' => $variations,
			'extra'      => $extra,
		];
	}
}
