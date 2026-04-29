<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\TestCase;

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
	 * Asserts that a validation issue has a context array containing an entry whose
	 * `specific_issue` value matches $expected_specific_issue, and returns that entry.
	 *
	 * @param array  $actual_issue            The issue array from ValidationIssue::to_array().
	 * @param string $expected_specific_issue The expected value of the `specific_issue` key.
	 * @return array The matched context entry (for further assertions by the caller).
	 */
	protected function assertIssueContext( array $actual_issue, string $expected_specific_issue ): array {
		$this->assertArrayHasKey( 'context', $actual_issue, 'Validation issue has no "context" key' );
		$this->assertIsArray( $actual_issue['context'], '"context" must be an array' );
		$this->assertNotEmpty( $actual_issue['context'], '"context" must be a non-empty array' );

		$found_values = array();

		foreach ( $actual_issue['context'] as $entry ) {
			$this->assertIsArray( $entry, 'Each context entry must be an array' );
			$this->assertArrayHasKey( 'specific_issue', $entry, 'Each context entry must have a "specific_issue" key' );

			if ( $entry['specific_issue'] === $expected_specific_issue ) {
				return $entry;
			}

			$found_values[] = $entry['specific_issue'];
		}

		$this->fail(
			sprintf(
				'No context entry found with specific_issue "%s". Found: [%s]',
				$expected_specific_issue,
				implode( ', ', $found_values )
			)
		);
	}

	protected function create_cart( string $item_id = '1', int $quantity = 1, string $name = 'Test Product' ): PayPalCart {
		return PayPalCart::from_array(
			array(
				'items'          => array(
					array(
						'item_id'  => $item_id,
						'quantity' => $quantity,
						'name'     => $name,
					),
				),
				'payment_method' => 'paypal',
			)
		);
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
