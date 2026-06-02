<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice;

use Mockery;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use function Brain\Monkey\Functions\when;

class PayUponInvoiceHelperTest extends TestCase
{
	private $checkout_helper;
	private $payment_settings;

	public function setUp(): void
	{
		parent::setUp();

		$this->checkout_helper  = Mockery::mock(CheckoutHelper::class);
		$this->payment_settings = Mockery::mock(PaymentSettings::class);

		// is_valid_currency() reads `order-pay` from the global $wp; default to no order.
		$GLOBALS['wp'] = (object) array('query_vars' => array());
	}

	public function tearDown(): void
	{
		unset($GLOBALS['wp']);
		parent::tearDown();
	}

	private function makeHelper(string $shop_country = 'DE'): PayUponInvoiceHelper
	{
		return new PayUponInvoiceHelper($this->checkout_helper, $shop_country, $this->payment_settings);
	}

	public function testIsValidCurrencyTrueForEur(): void
	{
		when('get_woocommerce_currency')->justReturn('EUR');

		$this->assertTrue($this->invokeIsValidCurrency($this->makeHelper()));
	}

	public function testIsValidCurrencyFalseForNonEur(): void
	{
		when('get_woocommerce_currency')->justReturn('USD');

		$this->assertFalse($this->invokeIsValidCurrency($this->makeHelper()));
	}

	public function testIsPuiGatewayEnabledTrueWhenEnabledAndShopCountryDe(): void
	{
		$this->payment_settings->shouldReceive('is_method_enabled')
			->with('ppcp-pay-upon-invoice-gateway')
			->andReturn(true);

		$this->assertTrue($this->makeHelper('DE')->is_pui_gateway_enabled());
	}

	public function testIsPuiGatewayEnabledFalseWhenShopCountryNotDe(): void
	{
		$this->payment_settings->shouldReceive('is_method_enabled')
			->with('ppcp-pay-upon-invoice-gateway')
			->andReturn(true);

		$this->assertFalse($this->makeHelper('US')->is_pui_gateway_enabled());
	}

	public function testIsPuiGatewayEnabledFalseWhenMethodDisabled(): void
	{
		$this->payment_settings->shouldReceive('is_method_enabled')
			->with('ppcp-pay-upon-invoice-gateway')
			->andReturn(false);

		$this->assertFalse($this->makeHelper('DE')->is_pui_gateway_enabled());
	}

	public function testIsCheckoutReadyForPuiFalseWhenBrandNameMissing(): void
	{
		$this->payment_settings->shouldReceive('get_pui_brand_name')->andReturn('');

		$this->assertFalse($this->makeHelper()->is_checkout_ready_for_pui());
	}

	public function testIsCheckoutReadyForPuiFalseWhenLogoMissing(): void
	{
		$this->payment_settings->shouldReceive('get_pui_brand_name')->andReturn('Acme');
		$this->payment_settings->shouldReceive('get_pui_logo_url')->andReturn('');

		$this->assertFalse($this->makeHelper()->is_checkout_ready_for_pui());
	}

	public function testIsCheckoutReadyForPuiFalseWhenCustomerServiceInstructionsMissing(): void
	{
		$this->payment_settings->shouldReceive('get_pui_brand_name')->andReturn('Acme');
		$this->payment_settings->shouldReceive('get_pui_logo_url')->andReturn('https://example.test/logo.png');
		$this->payment_settings->shouldReceive('get_pui_customer_service_instructions')->andReturn('');

		$this->assertFalse($this->makeHelper()->is_checkout_ready_for_pui());
	}

	private function invokeIsValidCurrency(PayUponInvoiceHelper $helper): bool
	{
		$method = new ReflectionMethod(PayUponInvoiceHelper::class, 'is_valid_currency');
		$method->setAccessible(true);

		return $method->invoke($helper);
	}
}
