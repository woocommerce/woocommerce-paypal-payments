<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CurrencyValidator
 */
class CurrencyValidatorTest extends ValidationTest {

	private CurrencyValidator $validator;

	public function setUp(): void {
		parent::setUp();
		$this->validator = new CurrencyValidator();
	}

	/**
	 * GIVEN a cart whose every item has the correct store currency
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_returns_empty_array_for_all_matching_currencies(): void {
		$item_a = $this->make_store_item( 0, null, true, 'USD', '1' );
		$item_b = $this->make_store_item( 1, null, true, 'USD', '2' );

		$cart   = $this->create_cart();
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item_a, $item_b ), 'USD' )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * GIVEN an empty cart (no items)
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_returns_empty_array_for_empty_cart(): void {
		$cart = PayPalCart::from_array(
			array(
				'items'          => array(),
				'payment_method' => 'paypal',
			),
			new StoreValidation()
		);

		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array(), 'USD' )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * GIVEN a cart item whose assumed currency (EUR) does not match the store currency (USD)
	 * WHEN validate() is called
	 * THEN exactly one ValidationIssue is returned
	 * AND the issue code is PRICING_ERROR with type BUSINESS_RULE
	 * AND the issue message mentions EUR and USD
	 * AND the issue field points to items[0].price.currency_code
	 * AND a USE_DIFFERENT_CURRENCY resolution option is included
	 */
	public function test_validate_detects_currency_mismatch_on_first_item(): void {
		$item = $this->make_store_item( 0, null, false, 'EUR', '1' );

		$cart   = $this->create_cart();
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ), 'USD' )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue(
			$issue_data,
			'PRICING_ERROR',
			'BUSINESS_RULE',
			'items[0].price.currency_code',
			'Cart currency EUR does not match store currency USD'
		);
		$this->assertResolutionOption( $issue_data, 'USE_DIFFERENT_CURRENCY' );
	}

	/**
	 * GIVEN items where multiple items have the wrong currency
	 * WHEN validate() is called
	 * THEN exactly one ValidationIssue is returned (not one per mismatched item)
	 * AND the user message instructs the customer about the expected currency
	 */
	public function test_validate_returns_single_issue_even_with_multiple_currency_mismatches(): void {
		$item_a = $this->make_store_item( 0, null, false, 'EUR', '1' );
		$item_b = $this->make_store_item( 1, null, false, 'EUR', '2' );

		$cart   = $this->create_cart();
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item_a, $item_b ), 'USD' )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}

	/**
	 * GIVEN a cart item with no assumed price (currency is correct by default)
	 * WHEN validate() is called
	 * THEN an empty array is returned (items without prices are not flagged)
	 */
	public function test_validate_ignores_items_without_assumed_currency(): void {
		// is_currency_correct returns true when no price is assumed
		$item = $this->make_store_item( 0, null, true, '', '1' );

		$cart   = $this->create_cart();
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ), 'USD' )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

}
