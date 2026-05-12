<?php
/**
 * Tests for ValidationIssue and all its factory methods.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorType;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
 */
class ValidationIssueTest extends TestCase {

	// -------------------------------------------------------------------------
	// Factory methods: correct code + type
	// -------------------------------------------------------------------------

	public function test_create_business_rule_violation(): void {
		$issue = ValidationIssue::create_business_rule_violation( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::BUSINESS_RULE_ERROR, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_coupon_invalid(): void {
		$issue = ValidationIssue::create_coupon_invalid( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::PRICING_ERROR, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_currency_mismatch(): void {
		$issue = ValidationIssue::create_currency_mismatch( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::PRICING_ERROR, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_insufficient_quantity(): void {
		$issue = ValidationIssue::create_insufficient_quantity( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::INVENTORY_ISSUE, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_item_out_of_stock(): void {
		$issue = ValidationIssue::create_item_out_of_stock( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::INVENTORY_ISSUE, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_price_mismatch(): void {
		$issue = ValidationIssue::create_price_mismatch( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::PRICING_ERROR, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_shipping_unavailable(): void {
		$issue = ValidationIssue::create_shipping_unavailable( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::SHIPPING_ERROR, $data['code'] );
		$this->assertSame( ErrorType::BUSINESS_RULE, $data['type'] );
	}

	public function test_create_invalid_data(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::DATA_ERROR, $data['code'] );
		$this->assertSame( ErrorType::INVALID_DATA, $data['type'] );
	}

	public function test_create_invalid_address(): void {
		$issue = ValidationIssue::create_invalid_address( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::SHIPPING_ERROR, $data['code'] );
		$this->assertSame( ErrorType::INVALID_DATA, $data['type'] );
	}

	public function test_create_invalid_product(): void {
		$issue = ValidationIssue::create_invalid_product( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::INVENTORY_ISSUE, $data['code'] );
		$this->assertSame( ErrorType::INVALID_DATA, $data['type'] );
	}

	public function test_create_missing_field(): void {
		$issue = ValidationIssue::create_missing_field( 'msg' );
		$data  = $issue->to_array();

		$this->assertSame( ErrorCode::DATA_ERROR, $data['code'] );
		$this->assertSame( ErrorType::MISSING_FIELD, $data['type'] );
	}

	// -------------------------------------------------------------------------
	// Builder methods
	// -------------------------------------------------------------------------

	public function test_message_is_included_in_output(): void {
		$issue = ValidationIssue::create_invalid_data( 'Something went wrong' );

		$this->assertSame( 'Something went wrong', $issue->to_array()['message'] );
	}

	public function test_message_is_truncated_to_255_chars(): void {
		$issue = ValidationIssue::create_invalid_data( str_repeat( 'x', 300 ) );

		$this->assertSame( 255, strlen( $issue->to_array()['message'] ) );
	}

	public function test_user_message_is_included_when_set(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->user_message( 'Please fix this.' );

		$this->assertSame( 'Please fix this.', $issue->to_array()['user_message'] );
	}

	public function test_user_message_is_omitted_when_not_set(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );

		$this->assertArrayNotHasKey( 'user_message', $issue->to_array() );
	}

	public function test_user_message_is_truncated_to_500_chars(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->user_message( str_repeat( 'x', 600 ) );

		$this->assertSame( 500, strlen( $issue->to_array()['user_message'] ) );
	}

	public function test_for_field_is_included_when_set(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->for_field( 'shipping_address.postal_code' );

		$this->assertSame( 'shipping_address.postal_code', $issue->to_array()['field'] );
	}

	public function test_for_field_is_omitted_when_not_set(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );

		$this->assertArrayNotHasKey( 'field', $issue->to_array() );
	}

	public function test_item_id_is_included_when_set(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->item_id( 'item_123' );

		$this->assertSame( 'item_123', $issue->to_array()['item_id'] );
	}

	public function test_item_id_is_omitted_when_not_set(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );

		$this->assertArrayNotHasKey( 'item_id', $issue->to_array() );
	}

	public function test_fluent_interface_returns_same_instance(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );

		$this->assertSame( $issue, $issue->user_message( 'x' ) );
		$this->assertSame( $issue, $issue->for_field( 'x' ) );
		$this->assertSame( $issue, $issue->item_id( 'x' ) );
	}

	// -------------------------------------------------------------------------
	// add_context
	// -------------------------------------------------------------------------

	public function test_add_context_with_single_context_object(): void {
		$context = $this->make_context( array( 'specific_issue' => 'FOO' ) );

		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->add_context( $context );

		$data = $issue->to_array();
		$this->assertArrayHasKey( 'context', $data );
		$this->assertSame( array( 'specific_issue' => 'FOO' ), $data['context'] );
	}

	public function test_add_context_with_multiple_contexts_only_first_is_serialized(): void {
		$contexts = array(
			$this->make_context( array( 'specific_issue' => 'A' ) ),
			$this->make_context( array( 'specific_issue' => 'B' ) ),
		);

		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->add_context( $contexts );

		$this->assertSame( array( 'specific_issue' => 'A' ), $issue->to_array()['context'] );
	}

	public function test_add_context_ignores_non_context_values(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->add_context( 'not_a_context' );

		$this->assertArrayNotHasKey( 'context', $issue->to_array() );
	}

	public function test_context_is_omitted_when_empty(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );

		$this->assertArrayNotHasKey( 'context', $issue->to_array() );
	}

	// -------------------------------------------------------------------------
	// add_resolution
	// -------------------------------------------------------------------------

	public function test_add_resolution_with_single_resolution_object(): void {
		when( 'wp_validate_redirect' )->returnArg( 1 );

		$resolution = ResolutionOption::create_remove_item()
			->label( 'Remove from cart' );

		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->add_resolution( $resolution );

		$data = $issue->to_array();
		$this->assertArrayHasKey( 'resolution_options', $data );
		$this->assertCount( 1, $data['resolution_options'] );
		$this->assertSame( 'REMOVE_ITEM', $data['resolution_options'][0]['action'] );
	}

	public function test_add_resolution_with_array_of_resolutions(): void {
		when( 'wp_validate_redirect' )->returnArg( 1 );

		$resolutions = array(
			ResolutionOption::create_remove_item()->label( 'Remove' ),
			ResolutionOption::create_modify_cart()->label( 'Modify' ),
		);

		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->add_resolution( $resolutions );

		$this->assertCount( 2, $issue->to_array()['resolution_options'] );
	}

	public function test_add_resolution_ignores_non_resolution_values(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' )
			->add_resolution( 'not_a_resolution' );

		$this->assertArrayNotHasKey( 'resolution_options', $issue->to_array() );
	}

	public function test_add_resolution_respects_max_limit_of_5(): void {
		when( 'wp_validate_redirect' )->returnArg( 1 );

		$issue = ValidationIssue::create_invalid_data( 'msg' );

		for ( $i = 0; $i < 7; $i ++ ) {
			$issue->add_resolution( ResolutionOption::create_remove_item()->label( "Option $i" ) );
		}

		$this->assertCount( 5, $issue->to_array()['resolution_options'] );
	}

	public function test_resolution_options_omitted_when_empty(): void {
		$issue = ValidationIssue::create_invalid_data( 'msg' );

		$this->assertArrayNotHasKey( 'resolution_options', $issue->to_array() );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_context( array $to_array_data ): IssueContext {
		return new class( $to_array_data ) extends IssueContext {
			private array $data;

			public function __construct( array $data ) {
				parent::__construct( $data['specific_issue'] ?? '' );
				$this->data = $data;
			}

			public function to_array(): array {
				return $this->data;
			}
		};
	}
}
