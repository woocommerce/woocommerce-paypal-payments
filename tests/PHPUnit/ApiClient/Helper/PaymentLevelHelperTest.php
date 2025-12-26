<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use function Brain\Monkey\Functions\expect;

class PaymentLevelHelperTest extends TestCase
{
	public function testBuildLevel2WithDefaultCustomerReference()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn(12345);
		$wcOrder->shouldReceive('get_currency')->andReturn('USD');
		$wcOrder->shouldReceive('get_total_tax')->andReturn(8.5);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_customer_reference',
				'12345',
				$wcOrder
			)
			->andReturn('12345');

		$helper = new PaymentLevelHelper();
		$result = $helper->build($wcOrder, 'level_2');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('supplementary_data', $result);
		$this->assertArrayHasKey('card', $result['supplementary_data']);
		$this->assertArrayHasKey('level_2', $result['supplementary_data']['card']);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertEquals('12345', $level2['customer_reference']);
		$this->assertEquals('USD', $level2['tax_total']['currency_code']);
		$this->assertEquals('8.50', $level2['tax_total']['value']);
	}

	public function testBuildLevel2FormatsDecimalTaxCorrectly()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn(12345);
		$wcOrder->shouldReceive('get_currency')->andReturn('USD');
		$wcOrder->shouldReceive('get_total_tax')->andReturn(12.456);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_customer_reference',
				'12345',
				$wcOrder
			)
			->andReturn('12345');

		$helper = new PaymentLevelHelper();
		$result = $helper->build($wcOrder, 'level_2');

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertEquals('12.46', $level2['tax_total']['value']);
	}

	public function testBuildLevel2WithDifferentCurrencies()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn(12345);
		$wcOrder->shouldReceive('get_currency')->andReturn('EUR');
		$wcOrder->shouldReceive('get_total_tax')->andReturn(15.0);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_customer_reference',
				'12345',
				$wcOrder
			)
			->andReturn('12345');

		$helper = new PaymentLevelHelper();
		$result = $helper->build($wcOrder, 'level_2');

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertEquals('EUR', $level2['tax_total']['currency_code']);
	}

	public function testBuildReturnsNullForInvalidLevel()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);

		$helper = new PaymentLevelHelper();
		$result = $helper->build($wcOrder, 'invalid_level');

		$this->assertNull($result);
	}

	public function testBuildLevel2ReturnsCorrectStructure()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn(99999);
		$wcOrder->shouldReceive('get_currency')->andReturn('USD');
		$wcOrder->shouldReceive('get_total_tax')->andReturn(25.75);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_customer_reference',
				'99999',
				$wcOrder
			)
			->andReturn('99999');

		$helper = new PaymentLevelHelper();
		$result = $helper->build($wcOrder, 'level_2');

		// Verify exact structure matches the array shape docblock
		$this->assertIsArray($result);
		$this->assertArrayHasKey('supplementary_data', $result);
		$this->assertIsArray($result['supplementary_data']);
		$this->assertArrayHasKey('card', $result['supplementary_data']);
		$this->assertIsArray($result['supplementary_data']['card']);
		$this->assertArrayHasKey('level_2', $result['supplementary_data']['card']);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertIsArray($level2);
		$this->assertArrayHasKey('customer_reference', $level2);
		$this->assertArrayHasKey('tax_total', $level2);
		$this->assertIsString($level2['customer_reference']);
		$this->assertIsArray($level2['tax_total']);
		$this->assertArrayHasKey('currency_code', $level2['tax_total']);
		$this->assertArrayHasKey('value', $level2['tax_total']);
		$this->assertIsString($level2['tax_total']['currency_code']);
		$this->assertIsString($level2['tax_total']['value']);
	}
}
