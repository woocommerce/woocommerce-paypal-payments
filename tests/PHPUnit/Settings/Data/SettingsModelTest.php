<?php

declare( strict_types = 1 );

namespace PHPUnit\Settings\Data;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

/**
 * Tests defensive bool coercion in SettingsModel typed-bool getters.
 *
 * Background: the typed-bool getters used to return $this->data[ $key ]
 * directly, trusting that whatever was stored under that key was a real
 * bool. On PHP 8.x this throws "Return value must be of type bool, string
 * returned" whenever the stored option contains a string -- which happens
 * after the legacy migration if the legacy option was stored in classic-WC
 * "yes"/"no" format, after a direct DB write, or after a third-party
 * import/export tool that re-serialises options without preserving types.
 *
 * The getters are now wrapped with DataSanitizer::sanitize_bool() which
 * uses filter_var( ..., FILTER_VALIDATE_BOOLEAN ) under the hood. This
 * preserves correct values, accepts every legacy on/off shape, and
 * falls back to the declared per-field default when the key is missing
 * entirely.
 */
class SettingsModelTest extends TestCase {

	/**
	 * Builds a SettingsModel where the persisted option contains exactly
	 * the value-under-test for $key, with no other keys present. This
	 * forces every other field to fall back to its declared default and
	 * isolates the getter under test from incidental coupling.
	 *
	 * @param string $key   The settings key under test.
	 * @param mixed  $value The persisted value to inject for that key.
	 */
	private function build_model_with( string $key, $value ): SettingsModel {
		expect( 'get_option' )
			->once()
			->with( 'woocommerce-ppcp-data-settings', array() )
			->andReturn( array( $key => $value ) );

		return new SettingsModel( new DataSanitizer(), 'wc-' );
	}

	/**
	 * Builds a SettingsModel where the persisted option is empty, so every
	 * getter falls back to the per-field default declared in get_defaults().
	 */
	private function build_model_with_empty_option(): SettingsModel {
		expect( 'get_option' )
			->once()
			->with( 'woocommerce-ppcp-data-settings', array() )
			->andReturn( array() );

		return new SettingsModel( new DataSanitizer(), 'wc-' );
	}

	// ------------------------------------------------------------------
	// The customer-reported regression: get_save_paypal_and_venmo
	// ------------------------------------------------------------------

	/**
	 * Regression test for the customer-reported TypeError.
	 *
	 * Reproduces the exact failure mode: a stored option containing the
	 * legacy WooCommerce "yes"/"no" string instead of a real bool. Before
	 * the fix, this getter would throw "Return value must be of type bool,
	 * string returned" on PHP 8.x.
	 *
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value Any of the shapes the option has
	 *                            historically been observed to hold.
	 * @param bool  $expected     Expected getter return value.
	 */
	public function test_get_save_paypal_and_venmo_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'save_paypal_and_venmo', $stored_value );

		$this->assertSame( $expected, $model->get_save_paypal_and_venmo() );
	}

	// ------------------------------------------------------------------
	// Same defensive behaviour applied uniformly to every bool getter
	// ------------------------------------------------------------------

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_authorize_only_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'authorize_only', $stored_value );

		$this->assertSame( $expected, $model->get_authorize_only() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_capture_virtual_orders_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'capture_virtual_orders', $stored_value );

		$this->assertSame( $expected, $model->get_capture_virtual_orders() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_instant_payments_only_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'instant_payments_only', $stored_value );

		$this->assertSame( $expected, $model->get_instant_payments_only() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_enable_contact_module_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'enable_contact_module', $stored_value );

		$this->assertSame( $expected, $model->get_enable_contact_module() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_save_card_details_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'save_card_details', $stored_value );

		$this->assertSame( $expected, $model->get_save_card_details() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_enable_pay_now_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'enable_pay_now', $stored_value );

		$this->assertSame( $expected, $model->get_enable_pay_now() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_enable_logging_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'enable_logging', $stored_value );

		$this->assertSame( $expected, $model->get_enable_logging() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_stay_updated_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'stay_updated', $stored_value );

		$this->assertSame( $expected, $model->get_stay_updated() );
	}

	/**
	 * @dataProvider bool_like_value_provider
	 *
	 * @param mixed $stored_value
	 * @param bool  $expected
	 */
	public function test_get_payment_level_processing_coerces_stored_value( $stored_value, bool $expected ): void {
		$model = $this->build_model_with( 'payment_level_processing', $stored_value );

		$this->assertSame( $expected, $model->get_payment_level_processing() );
	}

	// ------------------------------------------------------------------
	// Per-field defaults when the option is absent
	//
	// Different fields have different declared defaults in get_defaults().
	// Verify each one falls back to that default when the stored option
	// is empty -- the path third-party migration tools take when they
	// clear or recreate the option mid-process.
	// ------------------------------------------------------------------

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function default_value_provider(): array {
		return array(
			'authorize_only defaults to false'           => array( 'get_authorize_only', false ),
			'capture_virtual_orders defaults to false'   => array( 'get_capture_virtual_orders', false ),
			'save_paypal_and_venmo defaults to false'    => array( 'get_save_paypal_and_venmo', false ),
			'instant_payments_only defaults to false'    => array( 'get_instant_payments_only', false ),
			'enable_contact_module defaults to true'     => array( 'get_enable_contact_module', true ),
			'save_card_details defaults to false'        => array( 'get_save_card_details', false ),
			'enable_pay_now defaults to false'           => array( 'get_enable_pay_now', false ),
			'enable_logging defaults to false'           => array( 'get_enable_logging', false ),
			'stay_updated defaults to true'              => array( 'get_stay_updated', true ),
			'payment_level_processing defaults to true'  => array( 'get_payment_level_processing', true ),
		);
	}

	/**
	 * @dataProvider default_value_provider
	 *
	 * @param string $getter   The bool getter under test.
	 * @param bool   $expected The declared per-field default.
	 */
	public function test_getter_returns_declared_default_when_option_is_empty( string $getter, bool $expected ): void {
		$model = $this->build_model_with_empty_option();

		$this->assertSame( $expected, $model->$getter() );
	}

	// ------------------------------------------------------------------
	// Shared data provider for the coercion test family
	// ------------------------------------------------------------------

	/**
	 * Covers every shape the stored value has been observed to take in
	 * production, plus the boundary cases for filter_var(FILTER_VALIDATE_BOOLEAN).
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function bool_like_value_provider(): array {
		return array(
			// Real booleans -- the happy path. Must round-trip unchanged.
			'real bool true'                  => array( true, true ),
			'real bool false'                 => array( false, false ),

			// Legacy classic-WooCommerce "yes"/"no" storage. This is the
			// shape that produced the customer's TypeError.
			'legacy string yes'               => array( 'yes', true ),
			'legacy string no'                => array( 'no', false ),

			// Numeric-string storage common in form-posted settings.
			'string 1'                        => array( '1', true ),
			'string 0'                        => array( '0', false ),

			// Word forms accepted by filter_var FILTER_VALIDATE_BOOLEAN.
			'string true'                     => array( 'true', true ),
			'string false'                    => array( 'false', false ),
			'string on'                       => array( 'on', true ),
			'string off'                      => array( 'off', false ),

			// Empty / unrecognised string -- treat as falsey rather than
			// fatal.
			'empty string'                    => array( '', false ),
			'unrecognised string is falsy'    => array( 'maybe', false ),

			// Integers.
			'integer 1'                       => array( 1, true ),
			'integer 0'                       => array( 0, false ),
		);
	}
}
