<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\AppliedCoupon
 */
class AppliedCouponTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return AppliedCoupon::class;
	}

	protected function get_valid_data(): array {
		return array(
			'code'            => 'SAVE10',
			'description'     => '10% off entire order',
			'discount_amount' => array(
				'currency_code' => 'usd',
				'value'         => '4.00',
			),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'code'                          => 'SAVE10',
			'description'                   => '10% off entire order',
			'discount_amount.currency_code' => 'USD',
			'discount_amount.value'         => 4.0,
		);
	}

	protected function get_data_types(): array {
		return array(
			'code'        => 'string',
			'description' => 'string',
		);
	}

	public function test_required_fields(): void {
		// AppliedCoupon has no required fields - all fields are optional.

		$this->assertOptionalField( 'code' );
		$this->assertOptionalField( 'description' );
		$this->assertOptionalField( 'discount_amount' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'code', 'SAVE10' );
		$this->assertWhitespaceTrimming( 'description', 'Discount' );

		$this->assertEmptyStringPreserved( 'code' );
		$this->assertEmptyStringPreserved( 'description' );

		$this->assertFieldIsCaseSensitive( 'code', 'sample' );
		$this->assertFieldIsCaseSensitive( 'description', 'sample' );
		$this->assertFieldAcceptsSpecialCharacters( 'code' );
		$this->assertFieldAcceptsSpecialCharacters( 'description' );
	}
}
