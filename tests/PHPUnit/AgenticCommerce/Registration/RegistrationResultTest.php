<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Registration;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers RegistrationResult
 */
class RegistrationResultTest extends TestCase {

	public function test_can_be_instantiated_with_success(): void {
		$result = new RegistrationResult(
			true,
			'Registration successful',
			null
		);

		$this->assertTrue( $result->success );
		$this->assertSame( 'Registration successful', $result->message );
		$this->assertNull( $result->error );
	}

	public function test_can_be_instantiated_with_error(): void {
		$result = new RegistrationResult(
			false,
			'Operation failed',
			'Webhook connection timeout'
		);

		$this->assertFalse( $result->success );
		$this->assertSame( 'Operation failed', $result->message );
		$this->assertSame( 'Webhook connection timeout', $result->error );
	}
}
