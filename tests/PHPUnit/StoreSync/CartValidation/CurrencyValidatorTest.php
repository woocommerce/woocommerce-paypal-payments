<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CurrencyValidator
 */
class CurrencyValidatorTest extends ValidationTest {

	private CurrencyValidator $validator;

	public function setUp(): void {
		parent::setUp();
		$store_currency  = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );
		$this->validator = new CurrencyValidator( $store_currency );
	}

	public function test_validate_returns_null_for_valid_cart(): void {

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
		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'USD', 'value' => 10.0 ),
				array( 'currency' => 'EUR', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'PRICING_ERROR', 'BUSINESS_RULE', 'items[1].price.currency_code', 'Mixed currencies detected' );
	}

	public function test_validate_detects_store_currency_mismatch(): void {
		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'EUR', 'value' => 10.0 ),
				array( 'currency' => 'EUR', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'PRICING_ERROR', 'BUSINESS_RULE', 'items[0].price.currency_code', 'Cart currency EUR does not match store currency USD' );
	}

	public function test_validate_returns_null_for_empty_cart(): void {
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

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'PRICING_ERROR', 'BUSINESS_RULE', 'items[2].price.currency_code', 'Mixed currencies detected' );
	}

	public function test_store_mismatch_points_to_correct_index(): void {
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

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'PRICING_ERROR', 'BUSINESS_RULE', 'items[1].price.currency_code', 'Cart currency EUR does not match store currency USD' );
	}

	public function test_mixed_currency_prevents_store_check(): void {
		$store_currency  = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'GBP' );
		$this->validator = new CurrencyValidator( $store_currency );

		$cart = $this->create_cart_with_items(
			array(
				array( 'currency' => 'USD', 'value' => 10.0 ),
				array( 'currency' => 'EUR', 'value' => 20.0 ),
			)
		);

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$issue = $result[0]->to_array();
		$this->assertValidationIssue( $issue, 'PRICING_ERROR', 'BUSINESS_RULE', null, 'Mixed currencies detected' );
	}

}
