<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use Brain\Monkey\Functions;

class PaymentLevelEligibilityTest extends TestCase {

	private $settings;
	private $currency_getter;
	private PaymentLevelEligibility $testee;

	public function setUp(): void {
		parent::setUp();

		$this->settings = Mockery::mock( SettingsProvider::class );
		$this->currency_getter = Mockery::mock( CurrencyGetter::class );
	}

	/**
	 * Test eligibility when all conditions are met.
	 */
	public function test_is_eligible_when_all_conditions_met(): void {
		$this->settings->expects('is_payment_level_processing_enabled')->once()->andReturn(true);
		$this->currency_getter->expects('get')->once()->andReturn('USD');

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_countries', ['US'])
			->andReturn(['US']);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_currencies', ['USD'])
			->andReturn(['USD']);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_payment_methods', ['ppcp-credit-card-gateway'])
			->andReturn(['ppcp-credit-card-gateway']);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_eligible', true)
			->andReturn(true);

		$this->testee = new PaymentLevelEligibility(
			$this->settings,
			'US',
			$this->currency_getter
		);

		$result = $this->testee->is_eligible('ppcp-credit-card-gateway');

		$this->assertTrue($result);
	}

	/**
	 * Test eligibility fails when feature setting is disabled.
	 */
	public function test_is_not_eligible_when_feature_disabled(): void {
		$this->settings->expects('is_payment_level_processing_enabled')->once()->andReturn(false);

		$this->testee = new PaymentLevelEligibility(
			$this->settings,
			'US',
			$this->currency_getter
		);

		$result = $this->testee->is_eligible('ppcp-credit-card-gateway');

		$this->assertFalse($result);
	}

	/**
	 * Test eligibility fails with invalid country.
	 */
	public function test_is_not_eligible_with_invalid_country(): void {
		$this->settings->expects('is_payment_level_processing_enabled')->once()->andReturn(true);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_countries', ['US'])
			->andReturn(['US']);

		$this->testee = new PaymentLevelEligibility(
			$this->settings,
			'GB', // UK not allowed
			$this->currency_getter
		);

		$result = $this->testee->is_eligible('ppcp-credit-card-gateway');

		$this->assertFalse($result);
	}

	/**
	 * Test eligibility fails with invalid currency.
	 */
	public function test_is_not_eligible_with_invalid_currency(): void {
		$this->settings->expects('is_payment_level_processing_enabled')->once()->andReturn(true);
		$this->currency_getter->expects('get')->once()->andReturn('EUR');

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_countries', ['US'])
			->andReturn(['US']);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_currencies', ['USD'])
			->andReturn(['USD']);

		$this->testee = new PaymentLevelEligibility(
			$this->settings,
			'US',
			$this->currency_getter
		);

		$result = $this->testee->is_eligible('ppcp-credit-card-gateway');

		$this->assertFalse($result);
	}

	/**
	 * Test eligibility fails with invalid payment method.
	 */
	public function test_is_not_eligible_with_invalid_payment_method(): void {
		$this->settings->expects('is_payment_level_processing_enabled')->once()->andReturn(true);
		$this->currency_getter->expects('get')->once()->andReturn('USD');

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_countries', ['US'])
			->andReturn(['US']);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_currencies', ['USD'])
			->andReturn(['USD']);

		Functions\expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level_processing_payment_methods', ['ppcp-credit-card-gateway'])
			->andReturn(['ppcp-credit-card-gateway']);

		$this->testee = new PaymentLevelEligibility(
			$this->settings,
			'US',
			$this->currency_getter
		);

		$result = $this->testee->is_eligible('paypal'); // PayPal wallet not allowed

		$this->assertFalse($result);
	}
}
