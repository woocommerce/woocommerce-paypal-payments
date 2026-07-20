<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton;
use WooCommerce\PayPalCommerce\Applepay\Assets\DataToAppleButtonScripts;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;

/**
 * @covers \WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton
 */
class ApplePayButtonTest extends TestCase
{
	private $settings;
	private $context;
	private ApplePayButton $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->context  = Mockery::mock(Context::class);

		$this->sut = new ApplePayButton(
			$this->settings,
			Mockery::mock(PaymentSettings::class),
			Mockery::mock(LoggerInterface::class),
			Mockery::mock(OrderProcessor::class),
			Mockery::mock(AssetGetter::class),
			'1.0.0',
			Mockery::mock(DataToAppleButtonScripts::class),
			Mockery::mock(CartProductsHelper::class),
			$this->context
		);
	}

	public function testDisabledWhenApplePayGloballyDisabled(): void
	{
		$this->settings->shouldReceive('applepay_enabled')->andReturn(false);

		$this->assertFalse($this->sut->is_enabled());
	}

	/**
	 * @scenario Regression test for PCP-4084. Same master-switch bug as Google Pay: disabling
	 *           "Enable payment methods in this location" for Classic Checkout must hide Apple
	 *           Pay even though it's still selected in that location's method list.
	 */
	public function testDisabledWhenLocationDisabledEvenIfMethodSelected(): void
	{
		$this->settings->shouldReceive('applepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', false, array(ApplePayGateway::ID))
		);

		$this->assertFalse($this->sut->is_enabled());
	}

	public function testEnabledWhenLocationEnabledAndMethodSelected(): void
	{
		$this->settings->shouldReceive('applepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array(ApplePayGateway::ID))
		);

		$this->assertTrue($this->sut->is_enabled());
	}

	public function testDisabledWhenLocationEnabledButMethodNotSelected(): void
	{
		$this->settings->shouldReceive('applepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array())
		);

		$this->assertFalse($this->sut->is_enabled());
	}
}
