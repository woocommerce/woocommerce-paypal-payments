<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Mockery;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Filters\expectAdded;

/**
 * @covers \WooCommerce\PayPalCommerce\Googlepay\GooglepayModule
 */
class GooglepayModuleTest extends TestCase
{
	private $container;
	private $settings;
	private $context;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->context  = Mockery::mock(Context::class);

		$this->container = Mockery::mock(ContainerInterface::class);
		$this->container->shouldReceive('get')->with('settings.settings-provider')->andReturn($this->settings);
		$this->container->shouldReceive('get')->with('button.helper.context')->andReturn($this->context);
	}

	/**
	 * Runs the module and captures the closure registered for
	 * `woocommerce_available_payment_gateways`, so it can be invoked directly in assertions.
	 */
	private function captured_available_payment_gateways_filter(): callable
	{
		$captured = null;

		expectAdded('woocommerce_available_payment_gateways')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured ) {
					$captured = $callback;
				}
			);

		( new GooglepayModule() )->run( $this->container );

		$this->assertIsCallable($captured);

		return $captured;
	}

	/**
	 * @scenario Regression test for PCP-4084. Merchant unchecked "Enable payment methods in
	 *           this location" for Classic Checkout, but Google Pay is still selected in that
	 *           location's method list. WooCommerce would otherwise keep listing Google Pay as
	 *           a selectable radio-button gateway; the filter must strip it out.
	 */
	public function testGatewayRemovedWhenLocationDisabledEvenIfMethodSelected(): void
	{
		$filter = $this->captured_available_payment_gateways_filter();

		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', false, array(GooglePayGateway::ID))
		);

		$methods = array( GooglePayGateway::ID => Mockery::mock('WC_Payment_Gateway') );

		$result = $filter($methods);

		$this->assertArrayNotHasKey(GooglePayGateway::ID, $result);
	}

	public function testGatewayKeptWhenLocationEnabledAndMethodSelected(): void
	{
		$filter = $this->captured_available_payment_gateways_filter();

		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array(GooglePayGateway::ID))
		);

		$gateway = Mockery::mock('WC_Payment_Gateway');
		$methods = array( GooglePayGateway::ID => $gateway );

		$result = $filter($methods);

		$this->assertSame($gateway, $result[GooglePayGateway::ID]);
	}

	public function testGatewayUntouchedOutsideCheckoutContext(): void
	{
		$filter = $this->captured_available_payment_gateways_filter();

		$this->context->shouldReceive('context')->andReturn('product');

		$gateway = Mockery::mock('WC_Payment_Gateway');
		$methods = array( GooglePayGateway::ID => $gateway );

		$result = $filter($methods);

		$this->assertSame($gateway, $result[GooglePayGateway::ID]);
	}
}
