<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use WC_Payment_Gateways;
use WooCommerce;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use function Brain\Monkey\Functions\when;

class DisabledFundingSourcesTest extends TestCase
{
	private $settings_provider;
	private $dcc_configuration;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings_provider = Mockery::mock(SettingsProvider::class);
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
		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'US');

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
		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'US');

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
			$this->settings_provider,
			[
				'card' => 'Credit or debit cards',
				'paypal' => 'PayPal',
				'foo' => 'Bar',
			],
			$this->dcc_configuration,
			'US'
		);

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

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'MX');

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

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'MX');

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

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'CA');

		$this->setWcPaymentGateways();
		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		// For non-Mexico countries, existing logic applies
		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	private function setWcPaymentGateways(array $disabledFundings = []): void
	{
		$gateway           = Mockery::mock();
		$gateway->settings = array(
			'disable_funding' => $disabledFundings,
		);

		$paymentGateways = array(
			'ppcp-gateway' => $gateway,
		);

		$payment_gateways_mock = Mockery::mock( WC_Payment_Gateways::class );
		$payment_gateways_mock->shouldReceive( 'payment_gateways' )
			->andReturn( $paymentGateways );

		$woocommerce = Mockery::mock( WooCommerce::class );
		$woocommerce->shouldReceive( 'payment_gateways' )
			->andReturn( $payment_gateways_mock );

		when( 'WC' )->justReturn( $woocommerce );
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
