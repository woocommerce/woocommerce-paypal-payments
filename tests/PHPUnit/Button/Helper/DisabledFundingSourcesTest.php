<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use WC_Payment_Gateways;
use WooCommerce;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use function Brain\Monkey\Functions\when;

class DisabledFundingSourcesTest extends TestCase
{
	private $settings;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(Settings::class);
	}

	public function test_is_checkout_true_does_not_add_card()
	{
		$sut = new DisabledFundingSources($this->settings, []);

		$this->setExpectations();
		$this->setWcPaymentGateways();

		when('is_checkout')->justReturn(true);

		$this->assertEquals([], $sut->sources(''));
	}

	public function test_is_checkout_false_adds_card()
	{
		$sut = new DisabledFundingSources($this->settings, []);

		$this->setExpectations();
		$this->setWcPaymentGateways();

		when('is_checkout')->justReturn(false);

		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	public function test_checkout_block_context_adds_source()
	{
		$sut = new DisabledFundingSources($this->settings, [
			'card' => 'Credit or debit cards',
			'paypal' => 'PayPal',
			'foo' => 'Bar',
		]);

		$this->setExpectations();
		$this->setWcPaymentGateways();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['foo'], $sut->sources('checkout-block'));
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
}
