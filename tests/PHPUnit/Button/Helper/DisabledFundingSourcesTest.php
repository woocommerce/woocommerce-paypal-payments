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

	/**
	 * Block checkout page configured in WC "Checkout page" setting,
	 * `is_checkout` returns true when visiting the block checkout page.
	 */
	public function test_is_checkout_true_add_card_when_checkout_block_context()
	{
		$sut = new DisabledFundingSources($this->settings, []);

		$this->setExpectations();
		$this->setWcPaymentGateways();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['card'], $sut->sources('checkout-block'));
	}

	/**
	 * Classic checkout page configured in WC "Checkout page" setting,
	 * `is_checkout` returns false when visiting the block checkout page.
	 */
	public function test_is_checkout_false_add_card_when_checkout_context()
	{
		$sut = new DisabledFundingSources($this->settings, []);

		$this->setExpectations();
		$this->setWcPaymentGateways();

		when('is_checkout')->justReturn(false);

		$this->assertEquals(['card'], $sut->sources('checkout'));
	}

	public function test_is_checkout_true_add_allowed_sources_when_checkout_block_context()
	{
		$sut = new DisabledFundingSources($this->settings, [
			'card' => 'Credit or debit cards',
			'paypal' => 'PayPal',
			'foo' => 'Bar',
		]);

		$this->setExpectations();
		$this->setWcPaymentGateways();

		when('is_checkout')->justReturn(true);

		$this->assertEquals(['card', 'foo'], $sut->sources('checkout-block'));
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
