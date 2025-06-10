<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use WC_Payment_Gateways;
use WooCommerce;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use function Brain\Monkey\Functions\when;

class DisabledFundingSourcesTest extends TestCase
{
	private $settings;
	private $dcc_configuration;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(Settings::class);
		$this->dcc_configuration = Mockery::mock(CardPaymentsConfiguration::class);
	}

	/**
	 * Block checkout page configured in WC "Checkout page" setting,
	 * `is_checkout` returns true when visiting the block checkout page.
	 */
	public function test_is_checkout_true_add_card_when_checkout_block_context()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);
		$sut = new DisabledFundingSources($this->settings, [], $this->dcc_configuration, 'US');

		$this->setExpectations();
		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	/**
	 * Classic checkout page configured in WC "Checkout page" setting,
	 * `is_checkout` returns false when visiting the block checkout page.
	 */
	public function test_is_checkout_false_add_card_when_checkout_context()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);
		$sut = new DisabledFundingSources($this->settings, [], $this->dcc_configuration, 'US');

		$this->setExpectations();
		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(false);

		$this->assertEquals(['card'], $sut->sources('checkout'));
	}

	public function test_is_checkout_true_add_allowed_sources_when_checkout_block_context()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);
		$sut = new DisabledFundingSources(
			$this->settings,
			[
				'card' => 'Credit or debit cards',
				'paypal' => 'PayPal',
				'foo' => 'Bar',
			],
			$this->dcc_configuration,
			'US'
		);

		$this->setExpectations();
		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['card', 'foo'], $sut->sources('checkout-block'));
	}

	/**
	 * Test Mexico-specific logic: BCDC enabled should not disable card funding
	 */
	public function test_mexico_bcdc_enabled_does_not_disable_card_funding()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(false); // Changed to false
		$this->dcc_configuration->shouldReceive('is_bcdc_enabled')->andReturn(true);

		$sut = new DisabledFundingSources($this->settings, [], $this->dcc_configuration, 'MX');

		$this->setExpectations();
		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		// For Mexico with BCDC enabled, card should not be in disabled funding sources
		$this->assertEquals([], $sut->sources('checkout-block'));
	}

	/**
	 * Test Mexico-specific logic: BCDC disabled should disable card funding
	 */
	public function test_mexico_bcdc_disabled_disables_card_funding()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true); // This should cause card to be disabled
		$this->dcc_configuration->shouldReceive('is_bcdc_enabled')->andReturn(false);

		$sut = new DisabledFundingSources($this->settings, [], $this->dcc_configuration, 'MX');

		$this->setExpectations();
		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		// For Mexico with BCDC disabled, card should be in disabled funding sources
		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	/**
	 * Test non-Mexico country behavior remains unchanged
	 */
	public function test_non_mexico_country_behavior_unchanged()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);
		// BCDC method should not be called for non-Mexico countries

		$sut = new DisabledFundingSources($this->settings, [], $this->dcc_configuration, 'CA');

		$this->setExpectations();
		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		// For non-Mexico countries, existing logic applies
		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	private function setExpectations(
		array $disabledFundings = [],
		bool  $dccEnambled = true
	): void
	{
		$this->settings->shouldReceive('has')
			->with('disable_funding')
			->andReturn(true);

		$this->settings->shouldReceive('get')
			->with('disable_funding')
			->andReturn($disabledFundings);

		$this->settings->shouldReceive('has')
			->with('dcc_enabled')
			->andReturn(true);

		$this->settings->shouldReceive('get')
			->with('dcc_enabled')
			->andReturn($dccEnambled);
	}

	private function setWcPaymentGateways(array $paymentGateways = []): void
	{
		$woocommerce = Mockery::mock(WooCommerce::class);
		$payment_gateways = Mockery::mock(WC_Payment_Gateways::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->payment_gateways = $payment_gateways;
		$payment_gateways->shouldReceive('get_available_payment_gateways')
			->andReturn($paymentGateways);
	}

	/**
	 * Set up common WooCommerce function mocks
	 */
	private function setWooCommerceFunctionMocks(): void
	{
		when('wc_get_page_id')->justReturn(123);
		when('has_block')->justReturn(false);
		when('get_post')->justReturn((object) [
			'ID' => 123,
			'post_content' => 'Mock post content',
			'post_type' => 'page'
		]);
	}
}
