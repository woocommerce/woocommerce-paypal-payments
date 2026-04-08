<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
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
		$this->settings_provider->shouldReceive('venmo_enabled')->andReturn(true)->byDefault();
		$this->settings_provider->shouldReceive('button_styling')
			->andReturn(new LocationStylingDTO('', true, ['venmo']))->byDefault();

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
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(false);
		$this->dcc_configuration->shouldReceive('is_bcdc_enabled')->andReturn(true);

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'MX');

		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertEquals([], $sut->sources('checkout-block'));
	}

	/**
	 * Test Mexico-specific logic: BCDC disabled should disable card funding
	 */
	public function test_mexico_bcdc_disabled_disables_card_funding()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);
		$this->dcc_configuration->shouldReceive('is_bcdc_enabled')->andReturn(false);

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'MX');

		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	/**
	 * Test non-Mexico country behavior remains unchanged
	 */
	public function test_non_mexico_country_behavior_unchanged()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'CA');

		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	/**
	 * Test venmo is disabled when venmo_enabled setting is false
	 */
	public function test_venmo_disabled_when_setting_is_false()
	{
		$this->settings_provider = Mockery::mock(SettingsProvider::class);
		$this->settings_provider->shouldReceive('venmo_enabled')->andReturn(false);
		$this->settings_provider->shouldReceive('button_styling')
			->andReturn(new LocationStylingDTO('', true, ['venmo']));

		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'US');

		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertContains('venmo', $sut->sources('checkout-block'));
	}

	public function test_venmo_disabled_for_location_when_not_in_styling_methods()
	{
		$this->settings_provider->shouldReceive('button_styling')
			->andReturn(new LocationStylingDTO('', true, []));

		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'US');

		$this->setWooCommerceFunctionMocks();
		when('is_checkout')->justReturn(true);

		$this->assertContains('venmo', $sut->sources('checkout-block'));
	}

	/**
	 * Test venmo is not disabled when venmo_enabled setting is true
	 */
	public function test_venmo_enabled_when_setting_is_true()
	{
		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('use_acdc')->andReturn(true);

		$sut = new DisabledFundingSources($this->settings_provider, [], $this->dcc_configuration, 'US');

		$this->setWooCommerceFunctionMocks();

		when('is_checkout')->justReturn(true);

		$this->assertNotContains('venmo', $sut->sources('checkout-block'));
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
