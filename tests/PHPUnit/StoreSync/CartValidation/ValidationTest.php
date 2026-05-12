<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\TestCase;

abstract class ValidationTest extends TestCase {

	// ------------------------------------------------------------------------
	// Cart creation helpers.

	/**
	 * Create a simple test cart, used in several test cases.
	 */
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
			),
			new StoreValidation()
		);
	}

	protected function create_cart_with_shipping( array $address_data ): PayPalCart {
		return PayPalCart::from_array(
			array(
				'items'            => array(
					array(
						'item_id'  => '1',
						'quantity' => 1,
						'name'     => 'Test Product',
					),
				),
				'shipping_address' => $address_data,
				'payment_method'   => 'paypal',
			),
			new StoreValidation()
		);
	}

	protected function create_cart_with_coupons( array $coupons, float $subtotal = 50.00, string $customer_email = '' ): PayPalCart {
		$cart_data = array(
			'items'          => array(
				array(
					'item_id'  => '1',
					'quantity' => 1,
					'name'     => 'Test Product',
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => $subtotal,
					),
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		if ( ! empty( $coupons ) ) {
			$cart_data['coupons'] = $coupons;
		}

		if ( $customer_email ) {
			$cart_data['customer'] = array(
				'email_address' => $customer_email,
			);
		}

		return PayPalCart::from_array( $cart_data, new StoreValidation() );
	}

	protected function create_cart_with_items( array $items ): PayPalCart {
		$cart_items = array();

		foreach ( $items as $index => $item_data ) {
			$cart_items[] = array(
				'item_id'  => (string) ( $index + 1 ),
				'quantity' => 1,
				'name'     => "Item $index",
				'price'    => array(
					'currency_code' => $item_data['currency'],
					'value'         => $item_data['value'],
				),
			);
		}

		return PayPalCart::from_array(
			array(
				'items'          => $cart_items,
				'payment_method' => 'paypal',
			),
			new StoreValidation()
		);
	}

	/**
	 * Wraps a PayPalCart + optional StoreValidation + optional store items into a
	 * StorePayPalCart mock so it can be passed to validate( StorePayPalCart $store_cart ).
	 *
	 * @param PayPalCart        $paypal_cart  The PayPal cart schema.
	 * @param StoreValidation|null $validation Optional pre-existing validation state.
	 * @param StoreCartItem[]   $store_items  Optional resolved store items (from make_store_item).
	 * @param string            $currency     Store currency code (default 'USD').
	 */
	protected function wrap_in_store_cart(
		PayPalCart $paypal_cart,
		?StoreValidation $validation = null,
		array $store_items = array(),
		string $currency = 'USD'
	): StorePayPalCart {
		$validation = $validation ?? new StoreValidation();

		$store_cart = Mockery::mock( StorePayPalCart::class );
		$store_cart->allows( 'paypal_cart' )->andReturn( $paypal_cart );
		$store_cart->allows( 'validation' )->andReturn( $validation );
		$store_cart->allows( 'cart_items' )->andReturn( $store_items );
		$store_cart->allows( 'currency' )->andReturn( $currency );

		return $store_cart;
	}

	/**
	 * Creates a StoreCartItem Mockery stub with configurable properties.
	 *
	 * @param int         $index              Cart position (used by field_path()).
	 * @param mixed       $product            WC_Product mock; if null, a basic mock is created.
	 * @param bool        $is_currency_correct Whether the item currency matches the store.
	 * @param string      $assumed_currency   Currency code the agent assumed.
	 * @param string      $id                 Item identifier.
	 * @param int         $quantity           Requested quantity.
	 * @param mixed       $paypal_item        CartItem mock; if null, a basic mock is created.
	 * @return \Mockery\MockInterface
	 */
	protected function make_store_item(
		int $index = 0,
		$product = null,
		bool $is_currency_correct = true,
		string $assumed_currency = 'USD',
		string $id = '1',
		int $quantity = 1,
		$paypal_item = null
	) {
		if ( $product === null ) {
			$product = Mockery::mock( 'WC_Product' );
		}

		if ( $paypal_item === null ) {
			$paypal_item = Mockery::mock( CartItem::class );
		}

		$item = Mockery::mock( StoreCartItem::class );
		$item->allows( 'product' )->andReturn( $product );
		$item->allows( 'paypal_item' )->andReturn( $paypal_item );
		$item->allows( 'is_currency_correct' )->andReturn( $is_currency_correct );
		$item->allows( 'assumed_currency' )->andReturn( $assumed_currency );
		$item->allows( 'id' )->andReturn( $id );
		$item->allows( 'quantity' )->andReturn( $quantity );
		$item->allows( 'field_path' )->andReturnUsing(
			function ( string $child_path = '' ) use ( $index ): string {
				$child_path = trim( $child_path, '.' );
				return "items[{$index}]" . ( $child_path ? ".{$child_path}" : '' );
			}
		);

		return $item;
	}

	// ------------------------------------------------------------------------
	// Custom assertions.

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
	 * Asserts that a validation issue has a context object whose `specific_issue` value
	 * matches $expected_specific_issue, and returns that context object.
	 *
	 * @return array The context object (for further assertions by the caller).
	 */
	protected function assertIssueContext( array $actual_issue, string $expected_specific_issue ): array {
		$this->assertArrayHasKey( 'context', $actual_issue, 'Validation issue has no "context" key' );
		$context = $actual_issue['context'];
		$this->assertIsArray( $context, '"context" must be an array' );
		$this->assertNotEmpty( $context, '"context" must be non-empty' );
		$this->assertArrayHasKey( 'specific_issue', $context, '"context" must have a "specific_issue" key' );
		$this->assertSame(
			$expected_specific_issue,
			$context['specific_issue'],
			sprintf( 'Expected context specific_issue "%s", got "%s"', $expected_specific_issue, $context['specific_issue'] )
		);

		return $context;
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
