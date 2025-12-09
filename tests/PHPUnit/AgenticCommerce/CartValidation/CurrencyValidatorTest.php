<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\CurrencyMismatch;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CurrencyValidator
 */
class CurrencyValidatorTest extends TestCase {

	private CurrencyValidator $validator;

	public function setUp(): void {
		parent::setUp();
		$this->validator = new CurrencyValidator();
	}

	public function test_validate_returns_null_for_valid_cart(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'USD', 'value' => 10.0 ),
				array( 'currency' => 'USD', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_validate_detects_mixed_currencies(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'USD', 'value' => 10.0 ),
				array( 'currency' => 'EUR', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( CurrencyMismatch::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Mixed currencies detected', $issue_data['message'] );
		$this->assertSame( 'items[1].price.currency_code', $issue_data['field'] );
	}

	public function test_validate_detects_store_currency_mismatch(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'EUR', 'value' => 10.0 ),
				array( 'currency' => 'EUR', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( CurrencyMismatch::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Cart currency EUR does not match store currency USD', $issue_data['message'] );
		$this->assertStringContainsString( 'This store only accepts payments in USD', $issue_data['user_message'] );
		$this->assertSame( 'items[0].price.currency_code', $issue_data['field'] );
	}

	public function test_validate_returns_null_for_empty_cart(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart = PayPalCart::from_array(
			array(
				'items'          => array(),
				'payment_method' => 'paypal',
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_validates_with_some_items_without_prices(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart_data = array(
			'items'          => array(
				array(
					'item_id'  => '1',
					'quantity' => 1,
					'name'     => 'Item with price',
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => 10.0,
					),
				),
				array(
					'item_id'  => '2',
					'quantity' => 1,
					'name'     => 'Item without price',
					// No price field
				),
			),
			'payment_method' => 'paypal',
		);

		$cart   = PayPalCart::from_array( $cart_data );
		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_detects_mismatch_skipping_empty_items(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart_data = array(
			'items'          => array(
				array(
					'item_id'  => '1',
					'quantity' => 1,
					'name'     => 'Item without price',
					// No price field
				),
				array(
					'item_id'  => '2',
					'quantity' => 1,
					'name'     => 'Item with USD',
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => 10.0,
					),
				),
				array(
					'item_id'  => '3',
					'quantity' => 1,
					'name'     => 'Item with EUR',
					'price'    => array(
						'currency_code' => 'EUR',
						'value'         => 20.0,
					),
				),
			),
			'payment_method' => 'paypal',
		);

		$cart   = PayPalCart::from_array( $cart_data );
		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( CurrencyMismatch::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Mixed currencies detected', $issue_data['message'] );
		$this->assertStringContainsString( 'EUR', $issue_data['message'] );
		$this->assertStringContainsString( 'USD', $issue_data['message'] );
		$this->assertSame( 'items[2].price.currency_code', $issue_data['field'] );
	}

	public function test_store_mismatch_points_to_correct_index(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$cart_data = array(
			'items'          => array(
				array(
					'item_id'  => '1',
					'quantity' => 1,
					'name'     => 'Item without price',
					// No price field
				),
				array(
					'item_id'  => '2',
					'quantity' => 1,
					'name'     => 'Item with EUR',
					'price'    => array(
						'currency_code' => 'EUR',
						'value'         => 10.0,
					),
				),
			),
			'payment_method' => 'paypal',
		);

		$cart   = PayPalCart::from_array( $cart_data );
		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( CurrencyMismatch::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertStringContainsString( 'Cart currency EUR does not match store currency USD', $issue_data['message'] );
		$this->assertSame( 'items[1].price.currency_code', $issue_data['field'] );
	}

	public function test_mixed_currency_prevents_store_check(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'GBP' );

		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'USD', 'value' => 10.0 ),
				array( 'currency' => 'EUR', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( CurrencyMismatch::class, $result[0] );

		$issue = $result[0]->to_array();
		$this->assertStringContainsString( 'Mixed currencies detected', $issue['message'] );
	}

	/**
	 * Helper method to create a cart with items.
	 *
	 * @param array $items Array of items with currency and value.
	 * @return PayPalCart
	 */
	private function create_cart_with_items( array $items ): PayPalCart {
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
			)
		);
	}
}
