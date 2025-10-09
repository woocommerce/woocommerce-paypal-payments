<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers PaymentMethod
 */
class PaymentMethodTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return PaymentMethod::class;
	}

	protected function get_valid_data(): array {
		return array(
			'type' => 'paypal',
		);
	}
}
