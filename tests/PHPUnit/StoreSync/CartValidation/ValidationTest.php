<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Filters\expectApplied;

abstract class ValidationTest extends TestCase {
	/**
	 * Asserts that the provided actual issue is a valid validation issue and
	 * matches the expected issue-code and type.
	 */
	protected function assertValidationIssue( array $actual_issue, string $expected_code, string $expected_type, ?string $expected_field = null, ?string $expected_message_substring = null ): void {
		$this->assertIsArray( $actual_issue, 'Validation issue must be an array' );

		$this->assertArrayHasKey( 'code', $actual_issue, 'Validation issue needs a "code"' );
		$this->assertArrayHasKey( 'type', $actual_issue, 'Validation issue needs a "type"' );
		$this->assertArrayHasKey( 'message', $actual_issue, 'Validation issue needs a "message"' );

		$this->assertNotEmpty( $actual_issue['message'], 'Validation issue message must be a non-empty string' );

		$this->assertEquals( $expected_code, $actual_issue['code'], 'Validation issue has the wrong code' );
		$this->assertEquals( $expected_type, $actual_issue['type'], 'Validation issue has the wrong type' );

		if ( $expected_field !== null ) {
			$this->assertArrayHasKey( 'field', $actual_issue, 'Validation issue needs a "field"' );
			$this->assertEquals( $expected_field, $actual_issue['field'], 'Validation issue mentions the wrong field' );
		}

		if ( $expected_message_substring !== null ) {
			$this->assertStringContainsString( $expected_message_substring, $actual_issue['message'] );
		}
	}

	/**
	 * Asserts that a validation issue contains a resolution option with the given action.
	 *
	 * It checks all resolution options and only fails, if none of them matches the action.
	 */
	protected function assertResolutionOption( array $actual_issue, string $expected_action ): void {
		$this->assertIsArray( $actual_issue, 'Validation issue must be an array' );

		$this->assertArrayHasKey( 'resolution_options', $actual_issue, 'Validation issue has no resolution options, but expected to find some' );

		$this->assertIsArray( $actual_issue['resolution_options'], 'Resolution options are not an array' );

		foreach ( $actual_issue['resolution_options'] as $option ) {
			$this->assertIsArray( $option, 'Resolution option must be an array' );

			if ( $option['action'] === $expected_action ) {
				return;
			}
		}

		$this->fail( "No resolution option found with the action '$expected_action'." );
	}
}
