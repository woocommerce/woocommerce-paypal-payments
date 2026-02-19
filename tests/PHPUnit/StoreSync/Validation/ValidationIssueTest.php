<?php
/**
 * Tests all ValidationIssue classes:
 *
 * Those classes represent a validation problem and have no business logic; every class can be
 * tested using identical test cases.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\MissingField
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidData
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidProduct
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\ShippingUnavailable
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\PriceMismatch
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\ItemOutOfStock
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidAddress
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\InsufficientQuantity
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\CouponInvalid
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\CurrencyMismatch
 */
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

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_validation_issue_with_item_id( string $class_name ): void {
		$issue = new $class_name( 'Test message', 'User message', 'field_name', 'item_123' );
		$data  = $issue->to_array();

		$this->assertSame( 'item_123', $data['item_id'] );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_context_method( string $class_name ): void {
		$issue = new $class_name( 'Test message' );
		$issue->add_context( 'available_quantity', 5 )
			->add_context( 'requested_quantity', 10 );

		$data = $issue->to_array();

		$this->assertArrayHasKey( 'context', $data );
		$this->assertSame( 5, $data['context']['available_quantity'] );
		$this->assertSame( 10, $data['context']['requested_quantity'] );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_resolution_method( string $class_name ): void {
		$issue = new $class_name( 'Test message' );
		$issue->add_resolution( 'REMOVE_ITEM', 'Remove this item from cart' )
			->add_resolution( 'REDUCE_QUANTITY', 'Reduce quantity to available stock' );

		$data = $issue->to_array();

		$this->assertArrayHasKey( 'resolution_options', $data );
		$this->assertCount( 2, $data['resolution_options'] );
		$this->assertSame( 'REMOVE_ITEM', $data['resolution_options'][0]['action'] );
		$this->assertSame( 'Remove this item from cart', $data['resolution_options'][0]['label'] );
		$this->assertSame( 'REDUCE_QUANTITY', $data['resolution_options'][1]['action'] );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_resolution_with_url_and_metadata( string $class_name ): void {
		when( 'wp_validate_redirect' )->returnArg( 1 );

		$issue = new $class_name( 'Test message' );
		$issue->add_resolution(
			'SUGGEST_ALTERNATIVE',
			'View similar products',
			'https://store.com/alternatives',
			array( 'priority' => 'HIGH', 'cost_impact' => '$0.00' )
		);

		$data = $issue->to_array();

		$this->assertArrayHasKey( 'resolution_options', $data );
		$this->assertCount( 1, $data['resolution_options'] );

		$resolution = $data['resolution_options'][0];
		$this->assertSame( 'SUGGEST_ALTERNATIVE', $resolution['action'] );
		$this->assertSame( 'View similar products', $resolution['label'] );
		$this->assertSame( 'https://store.com/alternatives', $resolution['url'] );
		$this->assertSame( 'HIGH', $resolution['metadata']['priority'] );
		$this->assertSame( '$0.00', $resolution['metadata']['cost_impact'] );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_resolution_omits_empty_url_and_metadata( string $class_name ): void {
		$issue = new $class_name( 'Test message' );
		$issue->add_resolution( 'REMOVE_ITEM', 'Remove item' );

		$data       = $issue->to_array();
		$resolution = $data['resolution_options'][0];

		$this->assertArrayHasKey( 'action', $resolution );
		$this->assertArrayHasKey( 'label', $resolution );
		$this->assertArrayNotHasKey( 'url', $resolution );
		$this->assertArrayNotHasKey( 'metadata', $resolution );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_resolution_respects_max_limit( string $class_name ): void {
		$issue = new $class_name( 'Test message' );

		// Add 7 resolutions, only 5 should be kept.
		for ( $i = 1; $i <= 7; $i++ ) {
			$issue->add_resolution( "ACTION_$i", "Label $i" );
		}

		$data = $issue->to_array();

		$this->assertCount( 5, $data['resolution_options'] );
		$this->assertSame( 'ACTION_5', $data['resolution_options'][4]['action'] );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_fluent_interface_returns_same_instance( string $class_name ): void {
		$issue  = new $class_name( 'Test message' );
		$result = $issue->add_context( 'key', 'value' );

		$this->assertSame( $issue, $result );

		$result = $issue->add_resolution( 'ACTION', 'Label' );

		$this->assertSame( $issue, $result );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_resolution_validates_url( string $class_name ): void {
		// Mock wp_validate_redirect to return the URL for valid same-host URLs.
		when( 'wp_validate_redirect' )->alias(
			function ( $url, $fallback ) {
				// Simulate WordPress behavior: return URL if valid, fallback otherwise.
				if ( strpos( $url, 'https://merchant.com' ) === 0 ) {
					return $url;
				}
				return $fallback;
			}
		);

		$issue = new $class_name( 'Test message' );

		// Valid merchant URL should be accepted.
		$issue->add_resolution( 'REDIRECT', 'Go to store', 'https://merchant.com/products' );
		$data = $issue->to_array();
		$this->assertSame( 'https://merchant.com/products', $data['resolution_options'][0]['url'] );
	}

	/**
	 * @dataProvider validation_issue_provider
	 */
	public function test_add_resolution_rejects_invalid_url( string $class_name ): void {
		// Mock wp_validate_redirect to reject external/malicious URLs.
		when( 'wp_validate_redirect' )->alias(
			function ( $url, $fallback ) {
				// Simulate WordPress behavior: reject javascript: and external URLs.
				if ( strpos( $url, 'javascript:' ) === 0 || strpos( $url, 'https://evil.com' ) === 0 ) {
					return $fallback;
				}
				return $url;
			}
		);

		$issue = new $class_name( 'Test message' );

		// JavaScript URL should be rejected.
		$issue->add_resolution( 'MALICIOUS', 'Click me', 'javascript:alert(1)' );
		$data = $issue->to_array();
		$this->assertArrayNotHasKey( 'url', $data['resolution_options'][0] );
	}

	public function validation_issue_provider(): array {
		return array(
			'MissingField'         => array( MissingField::class ),
			'InvalidData'          => array( InvalidData::class ),
			'InvalidProduct'       => array( InvalidProduct::class ),
			'ShippingUnavailable'  => array( ShippingUnavailable::class ),
			'PriceMismatch'        => array( PriceMismatch::class ),
			'ItemOutOfStock'       => array( ItemOutOfStock::class ),
			'InvalidAddress'       => array( InvalidAddress::class ),
			'InsufficientQuantity' => array( InsufficientQuantity::class ),
			'CouponInvalid'        => array( CouponInvalid::class ),
			'CurrencyMismatch'     => array( CurrencyMismatch::class ),
		);
	}
}
