<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button;

class ProductPriceCalculatorTest extends SimulateCartTestCase {

	public function testCalculateTotalSimpleProduct(): void {
		$product = $this->createSimpleProduct( 25.00 );

		$products = [ $this->productData( $product, 2 ) ];

		$total = $this->price_calculator->calculate_total( $products );

		$expected = (float) wc_get_price_including_tax( $product, [ 'qty' => 2 ] );
		self::assertEquals( $expected, $total );
		self::assertGreaterThan( 0.0, $total );
	}

	public function testCalculateTotalVariableProduct(): void {
		$data      = $this->createVariableProduct( 15.00 );
		$parent    = $data['parent'];
		$variation = $data['variation'];

		$products = [
			$this->productData( $parent, 1, [
				[ 'name' => 'attribute_color', 'value' => 'red' ],
			] ),
		];

		$total = $this->price_calculator->calculate_total( $products );

		$expected = (float) wc_get_price_including_tax( $variation, [ 'qty' => 1 ] );
		self::assertEquals( $expected, $total );
		self::assertGreaterThan( 0.0, $total );
	}

	public function testCalculateTotalMultipleProducts(): void {
		$product_a = $this->createSimpleProduct( 10.00 );
		$product_b = $this->createSimpleProduct( 20.00 );

		$products = [
			$this->productData( $product_a, 1 ),
			$this->productData( $product_b, 3 ),
		];

		$total = $this->price_calculator->calculate_total( $products );

		$expected_a = (float) wc_get_price_including_tax( $product_a, [ 'qty' => 1 ] );
		$expected_b = (float) wc_get_price_including_tax( $product_b, [ 'qty' => 3 ] );
		self::assertEquals( $expected_a + $expected_b, $total );
	}

	public function testNeedsCartSimulationReturnsFalseForSimpleProduct(): void {
		$product  = $this->createSimpleProduct( 10.00 );
		$products = [ $this->productData( $product ) ];

		self::assertFalse( $this->price_calculator->needs_cart_simulation( $products ) );
	}

	public function testNeedsCartSimulationReturnsTrueWithExtraData(): void {
		$product  = $this->createSimpleProduct( 10.00 );
		$products = [ $this->productData( $product, 1, [], [ 'addon_field' => 'value' ] ) ];

		self::assertTrue( $this->price_calculator->needs_cart_simulation( $products ) );
	}

	public function testLightweightAndSimulationTotalsAgree(): void {
		$product  = $this->createSimpleProduct( 19.99 );
		$products = [ $this->productData( $product, 2 ) ];

		$lightweight_total = $this->price_calculator->calculate_total( $products );
		$simulation_result = $this->cart_simulator->simulate( $products );

		// For a simple product without coupons/fees, both paths should agree.
		self::assertEquals( $lightweight_total, $simulation_result['total'] );
	}
}
