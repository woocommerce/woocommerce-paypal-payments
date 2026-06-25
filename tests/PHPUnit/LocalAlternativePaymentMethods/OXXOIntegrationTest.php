<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Mockery;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use function Brain\Monkey\Functions\when;

class OXXOIntegrationTest extends TestCase
{
	private $checkout_helper;

	public function setUp(): void
	{
		parent::setUp();

		$this->checkout_helper = Mockery::mock(CheckoutHelper::class);
	}

	public function tearDown(): void
	{
		unset($_POST['country']);
		parent::tearDown();
	}

	public function testAllowedForMexicoInRange(): void
	{
		when('get_woocommerce_currency')->justReturn('MXN');
		$_POST['country'] = 'MX';
		$this->checkout_helper->shouldReceive('is_checkout_amount_allowed')->andReturn(true);

		$this->assertTrue($this->invokeCheckoutAllowed());
	}

	public function testRejectedWhenCurrencyIsNotMxn(): void
	{
		when('get_woocommerce_currency')->justReturn('USD');
		$_POST['country'] = 'MX';
		$this->checkout_helper->shouldReceive('is_checkout_amount_allowed')->andReturn(true);

		$this->assertFalse($this->invokeCheckoutAllowed());
	}

	public function testRejectedWhenBillingCountryIsNotMexico(): void
	{
		when('get_woocommerce_currency')->justReturn('MXN');
		$_POST['country'] = 'US';
		$this->checkout_helper->shouldReceive('is_checkout_amount_allowed')->andReturn(true);

		$this->assertFalse($this->invokeCheckoutAllowed());
	}

	public function testRejectedWhenAmountOutOfRange(): void
	{
		when('get_woocommerce_currency')->justReturn('MXN');
		$_POST['country'] = 'MX';
		$this->checkout_helper->shouldReceive('is_checkout_amount_allowed')->andReturn(false);

		$this->assertFalse($this->invokeCheckoutAllowed());
	}

	private function invokeCheckoutAllowed(): bool
	{
		$integration = new OXXOIntegration($this->checkout_helper);

		$method = new ReflectionMethod(OXXOIntegration::class, 'checkout_allowed_for_oxxo');
		$method->setAccessible(true);

		return $method->invoke($integration);
	}
}
