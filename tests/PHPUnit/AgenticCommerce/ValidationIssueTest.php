<?php
/**
 * Tests all ValidationIssue classes:
 *
 * Those classes represent a validation problem and have no business logic; every class can be
 * tested using identical test cases.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;

class ValidationIssueTest extends TestCase {

	private const VALID_CODES = array(
		'INVENTORY_ISSUE',
		'PRICING_ERROR',
		'SHIPPING_ERROR',
		'PAYMENT_ERROR',
		'DATA_ERROR',
		'BUSINESS_RULE_ERROR',
	);

	private const VALID_TYPES = array(
		'MISSING_FIELD',
		'INVALID_DATA',
		'BUSINESS_RULE',
	);

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_validation_issue_can_be_instantiated( string $class_name ): void {
		// Test with a fallback message.
		$issue = new $class_name( '' );
		$data  = $issue->to_array();

		$this->assertContains( $data['code'], self::VALID_CODES, "$class_name has invalid ISSUE_CODE" );
		$this->assertContains( $data['type'], self::VALID_TYPES, "$class_name has invalid ISSUE_TYPE" );
		$this->assertSame( 'Validation error occurred', $data['message'] );

		// Test with an actual message.
		$issue = new $class_name( 'Test message', 'User message', 'field_name' );
		$data  = $issue->to_array();

		$this->assertSame( 'Test message', $data['message'] );
		$this->assertSame( 'User message', $data['user_message'] );
		$this->assertSame( 'field_name', $data['field'] );
	}

	public function validation_issue_provider(): array {
		return array(
			'MissingField' => array( MissingField::class ),
			'InvalidData'  => array( InvalidData::class ),
		);
	}
}
