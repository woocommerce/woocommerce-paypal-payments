<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationResult
 */
class RegistrationResultTest extends TestCase {

	/**
	 * @dataProvider result_scenarios_provider
	 */
	public function test_stores_all_properties( bool $success, string $message, ?string $error ): void {
		$result = new RegistrationResult( $success, $message, $error );

		$this->assertSame( $success, $result->success );
		$this->assertSame( $message, $result->message );
		$this->assertSame( $error, $result->error );
	}

	public function result_scenarios_provider(): array {
		return array(
			'success'         => array( true, 'Registration successful', null ),
			'failure'         => array( false, 'Operation failed', 'Webhook connection timeout' ),
			'empty_message'   => array( false, '', 'Error occurred' ),
			'success_message' => array( true, 'Completed successfully', null ),
		);
	}
}
