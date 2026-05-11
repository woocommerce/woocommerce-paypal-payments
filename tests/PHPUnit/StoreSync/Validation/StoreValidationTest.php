<?php
/**
 * Tests for the StoreValidation collector.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation
 */
class StoreValidationTest extends TestCase {

	// -------------------------------------------------------------------------
	// Group 1 — Accumulation
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a freshly created StoreValidation collector
	 * WHEN nothing has been added yet
	 * THEN is_empty() returns true
	 * AND all() returns an empty array
	 */
	public function test_fresh_instance_is_empty(): void {
		$validation = new StoreValidation();

		$this->assertTrue( $validation->is_empty() );
		$this->assertSame( array(), $validation->all() );
	}

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN a single ValidationIssue is added via add()
	 * THEN is_empty() returns false
	 * AND all() contains exactly that issue
	 */
	public function test_add_single_issue_makes_collector_non_empty(): void {
		$validation = new StoreValidation();
		$issue      = ValidationIssue::create_invalid_data( 'test error' );

		$validation->add( $issue );

		$this->assertFalse( $validation->is_empty() );
		$this->assertSame( array( $issue ), $validation->all() );
	}

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN multiple ValidationIssue objects are added via add()
	 * THEN all() returns all of them in insertion order
	 */
	public function test_multiple_add_calls_preserve_insertion_order(): void {
		$validation = new StoreValidation();
		$first      = ValidationIssue::create_invalid_data( 'first' );
		$second     = ValidationIssue::create_invalid_data( 'second' );
		$third      = ValidationIssue::create_invalid_data( 'third' );

		$validation->add( $first );
		$validation->add( $second );
		$validation->add( $third );

		$this->assertSame( array( $first, $second, $third ), $validation->all() );
	}

	// -------------------------------------------------------------------------
	// Group 2 — Factory methods: add_missing_field (unique signature)
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN add_missing_field() is called with a field name and user message
	 * THEN the return value is a ValidationIssue instance
	 * AND is_empty() returns false
	 * AND all() contains exactly that issue
	 * AND the issue carries the expected field and user_message values
	 */
	public function test_add_missing_field_records_issue_with_field_and_user_message(): void {
		$validation = new StoreValidation();

		$issue = $validation->add_missing_field( 'billing_address', 'Billing address is required.' );

		$this->assertInstanceOf( ValidationIssue::class, $issue );
		$this->assertFalse( $validation->is_empty() );
		$this->assertSame( array( $issue ), $validation->all() );

		$data = $issue->to_array();
		$this->assertSame( 'billing_address', $data['field'] );
		$this->assertSame( 'Billing address is required.', $data['user_message'] );
	}

	// -------------------------------------------------------------------------
	// Group 2 — Factory methods: add_invalid_data (unique signature)
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN add_invalid_data() is called with a field, a reason, and a user message
	 * THEN the return value is a ValidationIssue instance
	 * AND is_empty() returns false
	 * AND all() contains exactly that issue
	 * AND the issue carries the expected field and user_message values
	 */
	public function test_add_invalid_data_records_issue_with_field_reason_and_user_message(): void {
		$validation = new StoreValidation();

		$issue = $validation->add_invalid_data( 'email', 'Malformed email address', 'Please enter a valid email.' );

		$this->assertInstanceOf( ValidationIssue::class, $issue );
		$this->assertFalse( $validation->is_empty() );
		$this->assertSame( array( $issue ), $validation->all() );

		$data = $issue->to_array();
		$this->assertSame( 'email', $data['field'] );
		$this->assertSame( 'Please enter a valid email.', $data['user_message'] );
	}

	// -------------------------------------------------------------------------
	// Group 2 — Factory methods: single-message variants (data provider)
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN a single-message factory method is called
	 * THEN the return value is a ValidationIssue instance
	 * AND is_empty() returns false
	 * AND all() contains exactly that returned issue
	 *
	 * @dataProvider single_message_factory_provider
	 */
	public function test_factory_method_records_issue_and_returns_it( string $method ): void {
		$validation = new StoreValidation();

		/** @var ValidationIssue $issue */
		$issue = $validation->$method( 'A test message.' );

		$this->assertInstanceOf( ValidationIssue::class, $issue );
		$this->assertFalse( $validation->is_empty() );
		$this->assertSame( array( $issue ), $validation->all() );
	}

	public function single_message_factory_provider(): array {
		return array(
			'add_coupon_invalid'           => array( 'add_coupon_invalid' ),
			'add_currency_mismatch'        => array( 'add_currency_mismatch' ),
			'add_insufficient_quantity'    => array( 'add_insufficient_quantity' ),
			'add_item_out_of_stock'        => array( 'add_item_out_of_stock' ),
			'add_price_mismatch'           => array( 'add_price_mismatch' ),
			'add_shipping_unavailable'     => array( 'add_shipping_unavailable' ),
			'add_invalid_address'          => array( 'add_invalid_address' ),
			'add_invalid_product'          => array( 'add_invalid_product' ),
			'add_business_rule_violation'  => array( 'add_business_rule_violation' ),
			'add_payment_error'            => array( 'add_payment_error' ),
		);
	}

	// -------------------------------------------------------------------------
	// Group 3 — has_issue_with_code
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN has_issue_with_code() is called on an empty collector
	 * THEN it returns false
	 */
	public function test_has_issue_with_code_returns_false_when_empty(): void {
		$validation = new StoreValidation();

		$this->assertFalse( $validation->has_issue_with_code( ErrorCode::PRICING_ERROR ) );
	}

	/**
	 * GIVEN a StoreValidation collector that contains an issue with a known code
	 * WHEN has_issue_with_code() is called with that same code
	 * THEN it returns true
	 */
	public function test_has_issue_with_code_returns_true_when_matching_issue_exists(): void {
		$validation = new StoreValidation();
		$validation->add_currency_mismatch( 'Currency mismatch detected.' );

		$this->assertTrue( $validation->has_issue_with_code( ErrorCode::PRICING_ERROR ) );
	}

	/**
	 * GIVEN a StoreValidation collector that contains issues but none with a specific code
	 * WHEN has_issue_with_code() is called with that specific code
	 * THEN it returns false
	 */
	public function test_has_issue_with_code_returns_false_when_no_matching_issue(): void {
		$validation = new StoreValidation();
		$validation->add_shipping_unavailable( 'No shipping to this location.' );

		$this->assertFalse( $validation->has_issue_with_code( ErrorCode::PRICING_ERROR ) );
	}

	// -------------------------------------------------------------------------
	// Group 3 — has_pricing_issue
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN has_pricing_issue() is called
	 * THEN it returns false
	 */
	public function test_has_pricing_issue_returns_false_when_empty(): void {
		$validation = new StoreValidation();

		$this->assertFalse( $validation->has_pricing_issue() );
	}

	/**
	 * GIVEN a StoreValidation collector with a PRICING_ERROR issue
	 * WHEN has_pricing_issue() is called
	 * THEN it returns true
	 */
	public function test_has_pricing_issue_returns_true_after_adding_pricing_error(): void {
		$validation = new StoreValidation();
		$validation->add_price_mismatch( 'Price changed since cart was created.' );

		$this->assertTrue( $validation->has_pricing_issue() );
	}

	// -------------------------------------------------------------------------
	// Group 3 — has_issue_for_field
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a fresh StoreValidation collector
	 * WHEN has_issue_for_field() is called
	 * THEN it returns false
	 */
	public function test_has_issue_for_field_returns_false_when_empty(): void {
		$validation = new StoreValidation();

		$this->assertFalse( $validation->has_issue_for_field( 'billing_address' ) );
	}

	/**
	 * GIVEN a StoreValidation collector with an issue attached to a specific field
	 * WHEN has_issue_for_field() is called with that same field name
	 * THEN it returns true
	 */
	public function test_has_issue_for_field_returns_true_when_matching_field_exists(): void {
		$validation = new StoreValidation();
		$validation->add_missing_field( 'shipping_address', 'Shipping address is required.' );

		$this->assertTrue( $validation->has_issue_for_field( 'shipping_address' ) );
	}

	/**
	 * GIVEN a StoreValidation collector with an issue attached to one field
	 * WHEN has_issue_for_field() is called with a different field name
	 * THEN it returns false
	 */
	public function test_has_issue_for_field_returns_false_for_different_field(): void {
		$validation = new StoreValidation();
		$validation->add_missing_field( 'shipping_address', 'Shipping address is required.' );

		$this->assertFalse( $validation->has_issue_for_field( 'billing_address' ) );
	}
}
