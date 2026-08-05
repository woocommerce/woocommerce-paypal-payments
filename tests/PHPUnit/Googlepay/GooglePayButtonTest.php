<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\Assets\GooglePayButton;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

/**
 * @covers \WooCommerce\PayPalCommerce\Googlepay\Assets\GooglePayButton
 */
class GooglePayButtonTest extends TestCase
{
	private $settings;
	private $context;
	private GooglePayButton $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->context  = Mockery::mock(Context::class);

		$this->sut = new GooglePayButton(
			Mockery::mock(AssetGetter::class),
			'https://example.test/sdk.js',
			'1.0.0',
			$this->settings,
			Mockery::mock(Environment::class),
			$this->context
		);
	}

	public function testDisabledWhenGooglePayGloballyDisabled(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(false);

		$this->assertFalse($this->sut->is_enabled());
	}

	/**
	 * @scenario Regression test for PCP-4084. A merchant unchecks "Enable payment methods in
	 *           this location" for Classic Checkout but leaves Google Pay selected in that
	 *           location's method list. The location's enabled flag is a master switch, so the
	 *           button must not render even though the method checkbox is still selected.
	 */
	public function testDisabledWhenLocationDisabledEvenIfMethodSelected(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', false, array(GooglePayGateway::ID))
		);

		$this->assertFalse($this->sut->is_enabled());
	}

	public function testEnabledWhenLocationEnabledAndMethodSelected(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array(GooglePayGateway::ID))
		);

		$this->assertTrue($this->sut->is_enabled());
	}

	public function testDisabledWhenLocationEnabledButMethodNotSelected(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array())
		);

		$this->assertFalse($this->sut->is_enabled());
	}
}
