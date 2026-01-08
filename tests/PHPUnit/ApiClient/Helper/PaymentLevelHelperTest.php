<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use function Brain\Monkey\Functions\expect;

class PaymentLevelHelperTest extends TestCase
{
	public function testBuildLevel2WithDefaultInvoiceId()
	{
		$taxTotal = Mockery::mock(Money::class);
		$taxTotal->shouldReceive('currency_code')->andReturn('USD');
		$taxTotal->shouldReceive('value_str')->andReturn('8.50');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn($taxTotal);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value; // Return the generated invoice ID
			});

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'level_2');

		$this->assertIsArray($result);
		$this->assertArrayHasKey('supplementary_data', $result);
		$this->assertArrayHasKey('card', $result['supplementary_data']);
		$this->assertArrayHasKey('level_2', $result['supplementary_data']['card']);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertStringStartsWith('INV_', $level2['invoice_id']);
		$this->assertEquals('USD', $level2['tax_total']['currency_code']);
		$this->assertEquals('8.50', $level2['tax_total']['value']);
	}

	public function testBuildLevel2WithCustomInvoiceId()
	{
		$taxTotal = Mockery::mock(Money::class);
		$taxTotal->shouldReceive('currency_code')->andReturn('USD');
		$taxTotal->shouldReceive('value_str')->andReturn('10.00');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn($taxTotal);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturn('CUSTOM-INV-12345');

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'level_2');

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertEquals('CUSTOM-INV-12345', $level2['invoice_id']);
	}

	public function testBuildLevel2TruncatesInvoiceIdTo127Characters()
	{
		$taxTotal = Mockery::mock(Money::class);
		$taxTotal->shouldReceive('currency_code')->andReturn('USD');
		$taxTotal->shouldReceive('value_str')->andReturn('5.00');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn($taxTotal);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		$longInvoiceId = str_repeat('A', 150); // 150 characters

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturn($longInvoiceId);

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'level_2');

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertEquals(127, strlen($level2['invoice_id']));
		$this->assertEquals(substr($longInvoiceId, 0, 127), $level2['invoice_id']);
	}

	public function testBuildLevel2WithNullTaxTotal()
	{
		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value;
			});

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'level_2');

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertArrayNotHasKey('tax_total', $level2);
	}

	public function testBuildLevel2WithNullBreakdown()
	{
		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn(null);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value;
			});

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'level_2');

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertArrayNotHasKey('tax_total', $level2);
	}

	public function testBuildReturnsNullForInvalidLevel()
	{
		$amount = Mockery::mock(Amount::class);

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'invalid_level');

		$this->assertNull($result);
	}

	public function testBuildLevel2ReturnsCorrectStructure()
	{
		$taxTotal = Mockery::mock(Money::class);
		$taxTotal->shouldReceive('currency_code')->andReturn('USD');
		$taxTotal->shouldReceive('value_str')->andReturn('25.75');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn($taxTotal);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value;
			});

		$helper = new PaymentLevelHelper();
		$result = $helper->build($amount, 'level_2');

		// Verify exact structure matches the array shape docblock
		$this->assertIsArray($result);
		$this->assertArrayHasKey('supplementary_data', $result);
		$this->assertIsArray($result['supplementary_data']);
		$this->assertArrayHasKey('card', $result['supplementary_data']);
		$this->assertIsArray($result['supplementary_data']['card']);
		$this->assertArrayHasKey('level_2', $result['supplementary_data']['card']);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertIsArray($level2);
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertArrayHasKey('tax_total', $level2);
		$this->assertIsString($level2['invoice_id']);
		$this->assertIsArray($level2['tax_total']);
		$this->assertArrayHasKey('currency_code', $level2['tax_total']);
		$this->assertArrayHasKey('value', $level2['tax_total']);
		$this->assertIsString($level2['tax_total']['currency_code']);
		$this->assertIsString($level2['tax_total']['value']);
	}
}
