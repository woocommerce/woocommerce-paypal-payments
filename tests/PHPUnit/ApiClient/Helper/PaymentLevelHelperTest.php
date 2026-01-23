<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use function Brain\Monkey\Functions\expect;

class PaymentLevelHelperTest extends TestCase
{
	private $settings;

	public function setUp(): void {
		parent::setUp();

		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->settings->shouldReceive('ships_from_postal_code')->andReturn('');
	}

	// ========== LEVEL 2 TESTS ==========

	public function testBuildLevel2WithDefaultInvoiceId()
	{
		$taxTotal = Mockery::mock(Money::class);
		$taxTotal->shouldReceive('currency_code')->andReturn('USD');
		$taxTotal->shouldReceive('value_str')->andReturn('8.50');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn($taxTotal);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value;
			});

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

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
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturn('CUSTOM-INV-12345');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertEquals('CUSTOM-INV-12345', $level2['invoice_id']);
	}

	public function testBuildLevel2WithNullTaxTotal()
	{
		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value;
			});

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertArrayNotHasKey('tax_total', $level2);
	}

	public function testBuildLevel2WithNullBreakdown()
	{
		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn(null);

		expect('apply_filters')
			->once()
			->with(
				'woocommerce_paypal_payments_level2_invoice_id',
				Mockery::pattern('/^INV_[A-Z0-9]+$/')
			)
			->andReturnUsing(function($hook, $value) {
				return $value;
			});

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertArrayNotHasKey('tax_total', $level2);
	}

	// ========== LEVEL 3 TESTS ==========

	public function testBuildLevel3WithShippingAmount()
	{
		$shippingAmount = Mockery::mock(Money::class);
		$shippingAmount->shouldReceive('currency_code')->andReturn('USD');
		$shippingAmount->shouldReceive('value_str')->andReturn('5.00');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn($shippingAmount);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$this->assertArrayHasKey('level_3', $result['supplementary_data']['card']);
		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('shipping_amount', $level3);
		$this->assertEquals('USD', $level3['shipping_amount']['currency_code']);
		$this->assertEquals('5.00', $level3['shipping_amount']['value']);
	}

	public function testBuildLevel3WithDiscountAmount()
	{
		$discountAmount = Mockery::mock(Money::class);
		$discountAmount->shouldReceive('currency_code')->andReturn('USD');
		$discountAmount->shouldReceive('value_str')->andReturn('3.00');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn($discountAmount);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('discount_amount', $level3);
		$this->assertEquals('USD', $level3['discount_amount']['currency_code']);
		$this->assertEquals('3.00', $level3['discount_amount']['value']);
	}

	public function testBuildLevel3WithDutyAmount()
	{
		$dutyAmount = Mockery::mock(Money::class);
		$dutyAmount->shouldReceive('currency_code')->andReturn('USD');
		$dutyAmount->shouldReceive('value_str')->andReturn('2.50');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn($dutyAmount);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('duty_amount', $level3);
		$this->assertEquals('USD', $level3['duty_amount']['currency_code']);
		$this->assertEquals('2.50', $level3['duty_amount']['value']);
	}

	public function testBuildLevel3WithShippingAddress()
	{
		$address = Mockery::mock(Address::class);
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'address_line_1' => '123 Main St',
			'admin_area_1' => 'CA',
			'admin_area_2' => 'San Francisco',
			'postal_code' => '94102',
		]);

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount, null, $shipping);

		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('shipping_address', $level3);
		$this->assertEquals('US', $level3['shipping_address']['country_code']);
		$this->assertEquals('123 Main St', $level3['shipping_address']['address_line_1']);
		$this->assertEquals('94102', $level3['shipping_address']['postal_code']);
	}

	public function testBuildLevel3WithShipsFromPostalCodeFromSettings()
	{
		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->settings->shouldReceive('ships_from_postal_code')->andReturn('12345');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '12345')
			->andReturn('12345');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('ships_from_postal_code', $level3);
		$this->assertEquals('12345', $level3['ships_from_postal_code']);
	}

	public function testBuildLevel3WithLineItems()
	{
		$unitAmount = Mockery::mock(Money::class);
		$unitAmount->shouldReceive('currency_code')->andReturn('USD');
		$unitAmount->shouldReceive('value_str')->andReturn('10.00');
		$unitAmount->shouldReceive('value')->andReturn(10.00);

		$tax = Mockery::mock(Money::class);
		$tax->shouldReceive('currency_code')->andReturn('USD');
		$tax->shouldReceive('value_str')->andReturn('1.00');

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('name')->andReturn('Test Product');
		$item->shouldReceive('quantity')->andReturn(2);
		$item->shouldReceive('unit_amount')->andReturn($unitAmount);
		$item->shouldReceive('description')->andReturn('Product description');
		$item->shouldReceive('sku')->andReturn('SKU-123');
		$item->shouldReceive('tax')->andReturn($tax);
		$item->shouldReceive('product_id')->andReturn(null);
		$item->shouldReceive('discount')->andReturn(null);

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn(null);
		$breakdown->shouldReceive('shipping')->andReturn(null);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_commodity_code', 'SKU-123', $item)
			->andReturn('SKU-123');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_upc', null, $item, '')
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_line_item_discount', null, $item)
			->andReturn(null);

		expect('get_option')
			->once()
			->with('woocommerce_weight_unit', 'lbs')
			->andReturn('lbs');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_unit_of_measure', 'POUND_GB_US', $item)
			->andReturn('POUND_GB_US');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount, [$item]);

		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('line_items', $level3);
		$this->assertCount(1, $level3['line_items']);

		$lineItem = $level3['line_items'][0];
		$this->assertEquals('Test Product', $lineItem['name']);
		$this->assertEquals('2', $lineItem['quantity']);
		$this->assertEquals('10.00', $lineItem['unit_amount']['value']);
		$this->assertEquals('20.00', $lineItem['total_amount']['value']);
		$this->assertEquals('Product description', $lineItem['description']);
		$this->assertEquals('SKU-123', $lineItem['commodity_code']);
		$this->assertEquals('1.00', $lineItem['tax']['value']);
		$this->assertEquals('POUND_GB_US', $lineItem['unit_of_measure']);
	}

	public function testBuildBothLevel2AndLevel3Together()
	{
		$taxTotal = Mockery::mock(Money::class);
		$taxTotal->shouldReceive('currency_code')->andReturn('USD');
		$taxTotal->shouldReceive('value_str')->andReturn('5.00');

		$shippingAmount = Mockery::mock(Money::class);
		$shippingAmount->shouldReceive('currency_code')->andReturn('USD');
		$shippingAmount->shouldReceive('value_str')->andReturn('10.00');

		$breakdown = Mockery::mock(AmountBreakdown::class);
		$breakdown->shouldReceive('tax_total')->andReturn($taxTotal);
		$breakdown->shouldReceive('shipping')->andReturn($shippingAmount);
		$breakdown->shouldReceive('discount')->andReturn(null);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('breakdown')->andReturn($breakdown);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level2_invoice_id', Mockery::any())
			->andReturnUsing(function($hook, $value) { return $value; });

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_duty_amount', null, $amount)
			->andReturn(null);

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_level3_ships_from_postal_code', '')
			->andReturn('');

		$helper = new PaymentLevelHelper($this->settings);
		$result = $helper->build($amount);

		// Verify both Level 2 and Level 3 are present
		$this->assertArrayHasKey('level_2', $result['supplementary_data']['card']);
		$this->assertArrayHasKey('level_3', $result['supplementary_data']['card']);

		// Verify Level 2 data
		$level2 = $result['supplementary_data']['card']['level_2'];
		$this->assertArrayHasKey('invoice_id', $level2);
		$this->assertEquals('5.00', $level2['tax_total']['value']);

		// Verify Level 3 data
		$level3 = $result['supplementary_data']['card']['level_3'];
		$this->assertEquals('10.00', $level3['shipping_amount']['value']);
	}
}
