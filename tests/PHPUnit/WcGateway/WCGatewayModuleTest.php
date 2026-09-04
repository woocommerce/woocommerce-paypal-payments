<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionMethod;
use WC_Session;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Functions\when;

class WCGatewayModuleTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	/**
	 * GIVEN the shopper's session still has a PCP gateway id chosen
	 * WHEN shopper_is_paying_with_ppcp is called
	 * THEN it returns true
	 *
	 * @dataProvider ppcp_chosen_payment_method_provider
	 */
	public function testReturnsTrueForPpcpChosenPaymentMethod(string $chosen_payment_method): void
	{
		$this->stub_wc_session_with_chosen_payment_method($chosen_payment_method);

		$this->assertTrue($this->invoke_shopper_is_paying_with_ppcp());
	}

	public function ppcp_chosen_payment_method_provider(): array
	{
		return [
			'main PayPal gateway' => ['ppcp-gateway'],
			'credit card gateway' => ['ppcp-credit-card-gateway'],
		];
	}

	/**
	 * GIVEN the shopper's session says they chose Direct Bank Transfer after a failed PayPal attempt
	 * WHEN shopper_is_paying_with_ppcp is called
	 * THEN it returns false, so the order's payment method is not forced back to PayPal
	 */
	public function testReturnsFalseWhenShopperChoseNonPpcpGateway(): void
	{
		$this->stub_wc_session_with_chosen_payment_method('bacs');

		$this->assertFalse($this->invoke_shopper_is_paying_with_ppcp());
	}

	/**
	 * GIVEN WC()->session holds a value that is not a chosen payment method string
	 * WHEN shopper_is_paying_with_ppcp is called
	 * THEN it returns false
	 *
	 * @dataProvider non_ppcp_session_value_provider
	 */
	public function testReturnsFalseForNonPpcpSessionValue($chosen_payment_method): void
	{
		$this->stub_wc_session_with_chosen_payment_method($chosen_payment_method);

		$this->assertFalse($this->invoke_shopper_is_paying_with_ppcp());
	}

	public function non_ppcp_session_value_provider(): array
	{
		return [
			'empty string'    => [''],
			'non-string array' => [['unexpected']],
			'non-string null' => [null],
		];
	}

	/**
	 * GIVEN there is no shopper session, e.g. on the admin order screen, in cron, or in a webhook
	 * WHEN shopper_is_paying_with_ppcp is called
	 * THEN it returns false without touching the session
	 */
	public function testReturnsFalseWhenWcSessionIsMissing(): void
	{
		$woocommerce = Mockery::mock(\WooCommerce::class);
		$woocommerce->session = null;
		when('WC')->justReturn($woocommerce);

		$this->assertFalse($this->invoke_shopper_is_paying_with_ppcp());
	}

	/**
	 * @param mixed $chosen_payment_method
	 */
	private function stub_wc_session_with_chosen_payment_method($chosen_payment_method): void
	{
		$session = Mockery::mock(WC_Session::class);
		$session->shouldReceive('get')
			->with('chosen_payment_method')
			->andReturn($chosen_payment_method);

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$woocommerce->session = $session;

		when('WC')->justReturn($woocommerce);
	}

	private function invoke_shopper_is_paying_with_ppcp(): bool
	{
		$module = new WCGatewayModule();

		$method = new ReflectionMethod($module, 'shopper_is_paying_with_ppcp');
		$method->setAccessible(true);

		return $method->invoke($module);
	}
}
