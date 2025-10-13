<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers GiftOptions
 */
class GiftOptionsTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return GiftOptions::class;
	}

	protected function get_valid_data(): array {
		return array();
	}

	/**
	 * Tests that GiftOptions stores and returns the is_gift flag.
	 */
	public function test_is_gift_accessor(): void {
		$data    = array( 'is_gift' => true );
		$options = GiftOptions::from_array( $data );

		$this->assertTrue( $options->is_gift() );
	}
}
