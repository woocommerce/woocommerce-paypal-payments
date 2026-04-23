<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PaymentLevelEligibility;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PaymentLevelHelper;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;

class PurchaseUnitFactoryTest extends TestCase
{
	private $wcOrderId = 1;
	private $wcOrderNumber = '100000';

	private $item;

	public function setUp(): void
	{
		parent::setUp();

		$this->item = Mockery::mock(Item::class, [
			'category' => Item::PHYSICAL_GOODS,
			'unit_amount' => new Money(42.5, 'USD'),
		]);
	}

	public function test_wc_order_default()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);
		$shipping = $this->create_shipping_mock($this->create_address_mock());

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->shouldReceive('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);
		$this->assertEquals('', $unit->description());
		$this->assertEquals('default', $unit->reference_id());
		$this->assertEquals($this->wcOrderId, $unit->custom_id());
		$this->assertEquals('', $unit->soft_descriptor());
		$this->assertEquals('WC-' . $this->wcOrderNumber, $unit->invoice_id());
		$this->assertEquals([$this->item], $unit->items());
		$this->assertEquals($amount, $unit->amount());
		$this->assertEquals($shipping, $unit->shipping());
	}

	public function test_wc_order_with_negative_fees()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);

		$fee = Mockery::mock(Item::class, [
			'category' => Item::DIGITAL_GOODS,
			'unit_amount' => new Money(10.0, 'USD'),
		]);
		$discount = Mockery::mock(Item::class, [
			'unit_amount' => new Money(-5, 'USD'),
		]);

		$shipping = $this->create_shipping_mock($this->create_address_mock());
		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->shouldReceive('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item, $fee, $discount], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);
		$this->assertEquals([$this->item, $fee], $unit->items());
	}

	public function test_wc_order_shipping_gets_dropped_when_no_postal_code()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);

		$address = Mockery::mock(Address::class);
		$address->expects('country_code')->twice()->andReturn('DE');
		$address->expects('postal_code')->andReturn('');
		$address->shouldReceive('address_line_1')->andReturn('Berlin Street');
		$address->shouldReceive('admin_area_2')->andReturn('Berlin');

		$shipping = Mockery::mock(Shipping::class);
		$shipping->expects('address')->andReturn($address);
		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_order_shipping_gets_dropped_when_no_country_code()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);

		// Address only stubs country_code — short-circuit drops shipping before other fields are read.
		$address = Mockery::mock(Address::class);
		$address->expects('country_code')->andReturn('');

		$shipping = Mockery::mock(Shipping::class);
		$shipping->expects('address')->andReturn($address);
		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_order_shipping_gets_dropped_when_no_address_line_1()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);

		// Short-circuit on empty address_line_1 stops before admin_area_2 is read.
		$address = Mockery::mock(Address::class);
		$address->expects('country_code')->andReturn('DE');
		$address->shouldReceive('postal_code')->andReturn('12345');
		$address->shouldReceive('address_line_1')->andReturn('');

		$shipping = Mockery::mock(Shipping::class);
		$shipping->expects('address')->andReturn($address);
		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_order_shipping_gets_dropped_when_no_city()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);
		$shipping = $this->create_shipping_mock(
			$this->create_address_mock('DE', '12345', 'Berlin Street', '')
		);
		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_order_shipping_gets_dropped_when_no_city_in_postal_code_exempt_country()
	{
		$wc_order = $this->create_wc_order_mock();
		$amount = Mockery::mock(Amount::class);
		$shipping = $this->create_shipping_mock(
			$this->create_address_mock('IE', '', 'Some Street', '')
		);
		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_order')->with($wc_order)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$this->item], 'from_wc_order', $wc_order),
			$shipping_factory
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_cart_default()
	{
		$wc_customer = Mockery::mock(\WC_Customer::class);
		expect('WC')->andReturn((object) ['customer' => $wc_customer, 'session' => null]);

		$wc_cart = Mockery::mock(\WC_Cart::class);
		$amount = Mockery::mock(Amount::class);
		$shipping = $this->create_shipping_mock($this->create_address_mock());

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_customer')->with($wc_customer, false)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_cart', $wc_cart),
			$this->create_item_factory_mock([$this->item], 'from_wc_cart', $wc_cart),
			$shipping_factory,
			null,
			null,
			$this->create_payment_level_eligibility_mock('', false)
		);

		$unit = $testee->from_wc_cart($wc_cart);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);
		$this->assertEquals('', $unit->description());
		$this->assertEquals('default', $unit->reference_id());
		$this->assertEquals('', $unit->custom_id());
		$this->assertEquals('', $unit->soft_descriptor());
		$this->assertEquals('', $unit->invoice_id());
		$this->assertEquals([$this->item], $unit->items());
		$this->assertEquals($amount, $unit->amount());
		$this->assertEquals($shipping, $unit->shipping());
	}

	public function test_wc_cart_shipping_gets_dropped_when_no_customer()
	{
		expect('WC')->andReturn((object) ['customer' => null, 'session' => null]);

		$wc_cart = Mockery::mock(\WC_Cart::class);
		$amount = Mockery::mock(Amount::class);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_cart', $wc_cart),
			$this->create_item_factory_mock([$this->item], 'from_wc_cart', $wc_cart),
			Mockery::mock(ShippingFactory::class),
			null,
			null,
			$this->create_payment_level_eligibility_mock('', false)
		);

		$unit = $testee->from_wc_cart($wc_cart);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_cart_shipping_gets_dropped_when_no_country_code()
	{
		expect('WC')->andReturn((object) ['customer' => Mockery::mock(\WC_Customer::class), 'session' => null]);

		$wc_cart = Mockery::mock(\WC_Cart::class);
		$amount = Mockery::mock(Amount::class);

		// Address only stubs country_code — short-circuit stops before other fields are read.
		$address = Mockery::mock(Address::class);
		$address->shouldReceive('country_code')->andReturn('');
		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_customer')->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_cart', $wc_cart),
			$this->create_item_factory_mock([$this->item], 'from_wc_cart', $wc_cart),
			$shipping_factory,
			null,
			null,
			$this->create_payment_level_eligibility_mock('', false)
		);

		$unit = $testee->from_wc_cart($wc_cart);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_cart_shipping_gets_dropped_when_no_city()
	{
		$wc_customer = Mockery::mock(\WC_Customer::class);
		expect('WC')->andReturn((object) ['customer' => $wc_customer, 'session' => null]);

		$wc_cart = Mockery::mock(\WC_Cart::class);
		$amount = Mockery::mock(Amount::class);
		$shipping = $this->create_shipping_mock(
			$this->create_address_mock('IE', '', 'Some Street', '')
		);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_customer')->with($wc_customer, false)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_cart', $wc_cart),
			$this->create_item_factory_mock([$this->item], 'from_wc_cart', $wc_cart),
			$shipping_factory,
			null,
			null,
			$this->create_payment_level_eligibility_mock('', false)
		);

		$unit = $testee->from_wc_cart($wc_cart);
		$this->assertNull($unit->shipping());
	}

	public function test_wc_cart_shipping_kept_when_ireland_has_city_no_postal_code()
	{
		$wc_customer = Mockery::mock(\WC_Customer::class);
		expect('WC')->andReturn((object) ['customer' => $wc_customer, 'session' => null]);

		$wc_cart = Mockery::mock(\WC_Cart::class);
		$amount = Mockery::mock(Amount::class);
		$shipping = $this->create_shipping_mock(
			$this->create_address_mock('IE', '', 'Some Street', 'Dublin')
		);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_customer')->with($wc_customer, false)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_cart', $wc_cart),
			$this->create_item_factory_mock([$this->item], 'from_wc_cart', $wc_cart),
			$shipping_factory,
			null,
			null,
			$this->create_payment_level_eligibility_mock('', false)
		);

		$unit = $testee->from_wc_cart($wc_cart);
		$this->assertEquals($shipping, $unit->shipping());
	}

	public function test_from_paypal_response_default()
	{
		$raw_item = (object) ['items' => 1];
		$raw_amount = (object) ['amount' => 1];
		$raw_shipping = (object) ['shipping' => 1];

		$amount = Mockery::mock(Amount::class);
		$shipping = Mockery::mock(Shipping::class);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_paypal_response')->with($raw_shipping)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_paypal_response', $raw_amount),
			$this->create_item_factory_from_response($raw_item),
			$shipping_factory
		);

		$response = (object) [
			'reference_id' => 'default',
			'description' => 'description',
			'custom_id' => 'customId',
			'invoice_id' => 'invoiceId',
			'soft_descriptor' => 'softDescriptor',
			'amount' => $raw_amount,
			'items' => [$raw_item],
			'shipping' => $raw_shipping,
		];

		$unit = $testee->from_paypal_response($response);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);
		$this->assertEquals('description', $unit->description());
		$this->assertEquals('default', $unit->reference_id());
		$this->assertEquals('customId', $unit->custom_id());
		$this->assertEquals('softDescriptor', $unit->soft_descriptor());
		$this->assertEquals('invoiceId', $unit->invoice_id());
		$this->assertEquals([$this->item], $unit->items());
		$this->assertEquals($amount, $unit->amount());
		$this->assertEquals($shipping, $unit->shipping());
	}

	public function test_from_paypal_response_shipping_is_null()
	{
		$raw_item = (object) ['items' => 1];
		$raw_amount = (object) ['amount' => 1];
		$amount = Mockery::mock(Amount::class);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_paypal_response', $raw_amount),
			$this->create_item_factory_from_response($raw_item),
			Mockery::mock(ShippingFactory::class)
		);

		$response = (object) [
			'reference_id' => 'default',
			'description' => 'description',
			'customId' => 'customId',
			'invoiceId' => 'invoiceId',
			'softDescriptor' => 'softDescriptor',
			'amount' => $raw_amount,
			'items' => [$raw_item],
		];

		$unit = $testee->from_paypal_response($response);
		$this->assertNull($unit->shipping());
	}

	public function test_from_paypal_response_needs_reference_id()
	{
		$testee = $this->create_testee(
			Mockery::mock(AmountFactory::class),
			Mockery::mock(ItemFactory::class),
			Mockery::mock(ShippingFactory::class)
		);

		$response = (object) [
			'description' => 'description',
			'customId' => 'customId',
			'invoiceId' => 'invoiceId',
			'softDescriptor' => 'softDescriptor',
			'amount' => '',
			'items' => [],
			'shipping' => '',
		];

		$this->expectException(\WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException::class);
		$testee->from_paypal_response($response);
	}

	public function test_from_paypal_response_payments_get_appended()
	{
		$raw_item = (object) ['items' => 1];
		$raw_amount = (object) ['amount' => 1];
		$raw_shipping = (object) ['shipping' => 1];
		$raw_payments = (object) ['payments' => 1];

		$amount = Mockery::mock(Amount::class);
		$item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
		$shipping = Mockery::mock(Shipping::class);
		$payments = Mockery::mock(Payments::class);

		$item_factory = Mockery::mock(ItemFactory::class);
		$item_factory->expects('from_paypal_response')->with($raw_item)->andReturn($item);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_paypal_response')->with($raw_shipping)->andReturn($shipping);

		$payments_factory = Mockery::mock(PaymentsFactory::class);
		$payments_factory->expects('from_paypal_response')->with($raw_payments)->andReturn($payments);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_paypal_response', $raw_amount),
			$item_factory,
			$shipping_factory,
			$payments_factory
		);

		$response = (object) [
			'reference_id' => 'default',
			'description' => 'description',
			'customId' => 'customId',
			'invoiceId' => 'invoiceId',
			'softDescriptor' => 'softDescriptor',
			'amount' => $raw_amount,
			'items' => [$raw_item],
			'shipping' => $raw_shipping,
			'payments' => $raw_payments,
		];

		$unit = $testee->from_paypal_response($response);
		$this->assertEquals($payments, $unit->payments());
	}

	public function test_from_paypal_response_payments_is_null()
	{
		$raw_item = (object) ['items' => 1];
		$raw_amount = (object) ['amount' => 1];
		$raw_shipping = (object) ['shipping' => 1];

		$amount = Mockery::mock(Amount::class);
		$item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
		$shipping = Mockery::mock(Shipping::class);

		$item_factory = Mockery::mock(ItemFactory::class);
		$item_factory->expects('from_paypal_response')->with($raw_item)->andReturn($item);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_paypal_response')->with($raw_shipping)->andReturn($shipping);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_paypal_response', $raw_amount),
			$item_factory,
			$shipping_factory
		);

		$response = (object) [
			'reference_id' => 'default',
			'description' => 'description',
			'customId' => 'customId',
			'invoiceId' => 'invoiceId',
			'softDescriptor' => 'softDescriptor',
			'amount' => $raw_amount,
			'items' => [$raw_item],
			'shipping' => $raw_shipping,
		];

		$unit = $testee->from_paypal_response($response);
		$this->assertNull($unit->payments());
	}

	public function test_wc_order_with_level_2_processing()
	{
		$wc_order = $this->create_wc_order_mock(CreditCardGateway::ID);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '100.00',
		]);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Test Item',
			'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
			'quantity' => '1',
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(100.0, 'USD'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$address = $this->create_address_mock('US', '12345', '123 Main St', 'Anytown');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'postal_code' => '12345',
			'address_line_1' => '123 Main St',
		]);

		$shipping = $this->create_shipping_mock($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '12345',
				'address_line_1' => '123 Main St',
			],
		]);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->shouldReceive('from_wc_order')->with($wc_order)->andReturn($shipping);

		$level_2_data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_12345',
						'tax_total' => ['currency_code' => 'USD', 'value' => '8.50'],
					],
				],
			],
		];

		$payment_level_helper = Mockery::mock(PaymentLevelHelper::class);
		$payment_level_helper->shouldReceive('build')->with($amount, [$item], $shipping)->andReturn($level_2_data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings->shouldReceive('is_payment_level_processing_enabled')->andReturn(true);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$item], 'from_wc_order', $wc_order),
			$shipping_factory,
			null,
			$payment_level_helper,
			$this->create_payment_level_eligibility_mock(CreditCardGateway::ID, true),
			$settings
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);

		$unit_array = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unit_array);
		$this->assertArrayHasKey('card', $unit_array['supplementary_data']);
		$this->assertArrayHasKey('level_2', $unit_array['supplementary_data']['card']);
		$this->assertEquals('INV_12345', $unit_array['supplementary_data']['card']['level_2']['invoice_id']);
		$this->assertEquals('USD', $unit_array['supplementary_data']['card']['level_2']['tax_total']['currency_code']);
		$this->assertEquals('8.50', $unit_array['supplementary_data']['card']['level_2']['tax_total']['value']);
	}

	public function test_wc_order_without_level_2_processing_when_not_eligible()
	{
		$wc_order = $this->create_wc_order_mock();

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'EUR',
			'value' => '100.00',
		]);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Test Item',
			'unit_amount' => ['currency_code' => 'EUR', 'value' => '100.00'],
			'quantity' => '1',
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(100.0, 'EUR'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$address = $this->create_address_mock();
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'DE',
			'postal_code' => '12345',
			'address_line_1' => 'Berlin Street',
		]);

		$shipping = $this->create_shipping_mock($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'DE',
				'postal_code' => '12345',
				'address_line_1' => 'Berlin Street',
			],
		]);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->shouldReceive('from_wc_order')->with($wc_order)->andReturn($shipping);

		$payment_level_helper = Mockery::mock(PaymentLevelHelper::class);
		$payment_level_helper->shouldNotReceive('build');

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$item], 'from_wc_order', $wc_order),
			$shipping_factory,
			null,
			$payment_level_helper
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);

		$unit_array = $unit->to_array();
		$this->assertArrayNotHasKey('supplementary_data', $unit_array);
	}

	public function test_wc_order_with_level_3_processing()
	{
		$wc_order = $this->create_wc_order_mock(CreditCardGateway::ID);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '115.00',
		]);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Test Product',
			'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
			'quantity' => '1',
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(100.0, 'USD'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$address = $this->create_address_mock('US', '94102', '123 Market St', 'San Francisco');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'postal_code' => '94102',
			'address_line_1' => '123 Market St',
		]);

		$shipping = $this->create_shipping_mock($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '94102',
				'address_line_1' => '123 Market St',
			],
		]);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->shouldReceive('from_wc_order')->with($wc_order)->andReturn($shipping);

		$level_3_data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => ['invoice_id' => 'INV_12345'],
					'level_3' => [
						'shipping_amount' => ['currency_code' => 'USD', 'value' => '10.00'],
						'discount_amount' => ['currency_code' => 'USD', 'value' => '5.00'],
						'shipping_address' => [
							'country_code' => 'US',
							'postal_code' => '94102',
							'address_line_1' => '123 Market St',
						],
						'ships_from_postal_code' => '12345',
						'line_items' => [
							[
								'name' => 'Test Product',
								'quantity' => '1',
								'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
								'total_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
								'commodity_code' => 'SKU-123',
								'unit_of_measure' => 'POUND_GB_US',
							],
						],
					],
				],
			],
		];

		$payment_level_helper = Mockery::mock(PaymentLevelHelper::class);
		$payment_level_helper->shouldReceive('build')->with($amount, [$item], $shipping)->andReturn($level_3_data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings->shouldReceive('is_payment_level_processing_enabled')->andReturn(true);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$item], 'from_wc_order', $wc_order),
			$shipping_factory,
			null,
			$payment_level_helper,
			$this->create_payment_level_eligibility_mock(CreditCardGateway::ID, true),
			$settings
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);

		$unit_array = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unit_array);
		$this->assertArrayHasKey('card', $unit_array['supplementary_data']);
		$this->assertArrayHasKey('level_3', $unit_array['supplementary_data']['card']);

		$level_3 = $unit_array['supplementary_data']['card']['level_3'];
		$this->assertArrayHasKey('shipping_amount', $level_3);
		$this->assertEquals('10.00', $level_3['shipping_amount']['value']);
		$this->assertArrayHasKey('discount_amount', $level_3);
		$this->assertEquals('5.00', $level_3['discount_amount']['value']);
		$this->assertArrayHasKey('shipping_address', $level_3);
		$this->assertEquals('US', $level_3['shipping_address']['country_code']);
		$this->assertArrayHasKey('ships_from_postal_code', $level_3);
		$this->assertEquals('12345', $level_3['ships_from_postal_code']);
		$this->assertArrayHasKey('line_items', $level_3);
		$this->assertCount(1, $level_3['line_items']);
		$this->assertEquals('Test Product', $level_3['line_items'][0]['name']);
		$this->assertEquals('SKU-123', $level_3['line_items'][0]['commodity_code']);
	}

	public function test_wc_order_with_both_level_2_and_level_3_processing()
	{
		$wc_order = $this->create_wc_order_mock(CreditCardGateway::ID);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '118.50',
		]);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Premium Widget',
			'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
			'quantity' => '1',
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(100.0, 'USD'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$address = $this->create_address_mock('US', '10001', '350 5th Ave', 'New York');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'postal_code' => '10001',
			'address_line_1' => '350 5th Ave',
		]);

		$shipping = $this->create_shipping_mock($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '10001',
				'address_line_1' => '350 5th Ave',
			],
		]);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->shouldReceive('from_wc_order')->with($wc_order)->andReturn($shipping);

		$combined_data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_COMBINED_789',
						'tax_total' => ['currency_code' => 'USD', 'value' => '8.50'],
					],
					'level_3' => [
						'shipping_amount' => ['currency_code' => 'USD', 'value' => '10.00'],
						'discount_amount' => ['currency_code' => 'USD', 'value' => '0.00'],
						'duty_amount' => ['currency_code' => 'USD', 'value' => '2.50'],
						'shipping_address' => [
							'country_code' => 'US',
							'postal_code' => '10001',
							'address_line_1' => '350 5th Ave',
						],
						'ships_from_postal_code' => '94102',
						'line_items' => [
							[
								'name' => 'Premium Widget',
								'quantity' => '1',
								'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
								'total_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
								'description' => 'High quality widget',
								'commodity_code' => 'WIDGET-001',
								'upc' => ['type' => 'UPC-A', 'code' => '012345678905'],
								'tax' => ['currency_code' => 'USD', 'value' => '8.50'],
								'unit_of_measure' => 'KILOGRAM',
							],
						],
					],
				],
			],
		];

		$payment_level_helper = Mockery::mock(PaymentLevelHelper::class);
		$payment_level_helper->shouldReceive('build')->with($amount, [$item], $shipping)->andReturn($combined_data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings->shouldReceive('is_payment_level_processing_enabled')->andReturn(true);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_order', $wc_order),
			$this->create_item_factory_mock([$item], 'from_wc_order', $wc_order),
			$shipping_factory,
			null,
			$payment_level_helper,
			$this->create_payment_level_eligibility_mock(CreditCardGateway::ID, true),
			$settings
		);

		$unit = $testee->from_wc_order($wc_order);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);

		$unit_array = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unit_array);
		$this->assertArrayHasKey('card', $unit_array['supplementary_data']);
		$this->assertArrayHasKey('level_2', $unit_array['supplementary_data']['card']);
		$this->assertArrayHasKey('level_3', $unit_array['supplementary_data']['card']);

		$level_2 = $unit_array['supplementary_data']['card']['level_2'];
		$this->assertEquals('INV_COMBINED_789', $level_2['invoice_id']);
		$this->assertEquals('USD', $level_2['tax_total']['currency_code']);
		$this->assertEquals('8.50', $level_2['tax_total']['value']);

		$level_3 = $unit_array['supplementary_data']['card']['level_3'];
		$this->assertEquals('10.00', $level_3['shipping_amount']['value']);
		$this->assertEquals('2.50', $level_3['duty_amount']['value']);
		$this->assertEquals('94102', $level_3['ships_from_postal_code']);

		$this->assertCount(1, $level_3['line_items']);
		$line_item = $level_3['line_items'][0];
		$this->assertEquals('Premium Widget', $line_item['name']);
		$this->assertEquals('WIDGET-001', $line_item['commodity_code']);
		$this->assertArrayHasKey('upc', $line_item);
		$this->assertEquals('UPC-A', $line_item['upc']['type']);
		$this->assertEquals('012345678905', $line_item['upc']['code']);
		$this->assertEquals('8.50', $line_item['tax']['value']);
		$this->assertEquals('KILOGRAM', $line_item['unit_of_measure']);
	}

	public function test_wc_cart_with_level_2_processing()
	{
		$wc_customer = Mockery::mock(\WC_Customer::class);
		expect('WC')->andReturn((object) ['customer' => $wc_customer, 'session' => null]);

		$wc_cart = Mockery::mock(\WC_Cart::class);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '58.50',
		]);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Cart Item',
			'unit_amount' => ['currency_code' => 'USD', 'value' => '50.00'],
			'quantity' => '1',
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(50.0, 'USD'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$address = $this->create_address_mock('US', '90210', 'Berlin Street', 'Beverly Hills');
		$shipping = $this->create_shipping_mock($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '90210',
			],
		]);

		$shipping_factory = Mockery::mock(ShippingFactory::class);
		$shipping_factory->expects('from_wc_customer')->with($wc_customer, false)->andReturn($shipping);

		$level_2_data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_CART_456',
						'tax_total' => ['currency_code' => 'USD', 'value' => '4.50'],
					],
				],
			],
		];

		$payment_level_helper = Mockery::mock(PaymentLevelHelper::class);
		$payment_level_helper->shouldReceive('build')->with($amount, [$item], $shipping)->andReturn($level_2_data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings->shouldReceive('is_payment_level_processing_enabled')->andReturn(true);

		$testee = $this->create_testee(
			$this->create_amount_factory_mock($amount, 'from_wc_cart', $wc_cart),
			$this->create_item_factory_mock([$item], 'from_wc_cart', $wc_cart),
			$shipping_factory,
			null,
			$payment_level_helper,
			$this->create_payment_level_eligibility_mock('', true),
			$settings
		);

		$unit = $testee->from_wc_cart($wc_cart);
		$this->assertInstanceOf(PurchaseUnit::class, $unit);

		$unit_array = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unit_array);
		$this->assertArrayHasKey('card', $unit_array['supplementary_data']);
		$this->assertArrayHasKey('level_2', $unit_array['supplementary_data']['card']);

		$level_2 = $unit_array['supplementary_data']['card']['level_2'];
		$this->assertEquals('INV_CART_456', $level_2['invoice_id']);
		$this->assertEquals('4.50', $level_2['tax_total']['value']);
	}

	// ---------------------------------------------------------------------
	// Private mock builders
	// ---------------------------------------------------------------------

	private function create_address_mock(
		string $country_code = 'DE',
		string $postal_code = '12345',
		string $address_line_1 = 'Berlin Street',
		string $admin_area_2 = 'Berlin'
	): Address {
		$address = Mockery::mock(Address::class);
		$address->shouldReceive('country_code')->andReturn($country_code);
		$address->shouldReceive('postal_code')->andReturn($postal_code);
		$address->shouldReceive('address_line_1')->andReturn($address_line_1);
		$address->shouldReceive('admin_area_2')->andReturn($admin_area_2);
		return $address;
	}

	private function create_shipping_mock(Address $address): Shipping
	{
		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->zeroOrMoreTimes()->andReturn($address);
		return $shipping;
	}

	private function create_amount_factory_mock(Amount $amount, string $source_method, $with): AmountFactory
	{
		$factory = Mockery::mock(AmountFactory::class);
		$factory->shouldReceive($source_method)->with($with)->andReturn($amount);
		return $factory;
	}

	private function create_item_factory_mock(array $items, string $source_method, $with): ItemFactory
	{
		$factory = Mockery::mock(ItemFactory::class);
		$factory->shouldReceive($source_method)->with($with)->andReturn($items);
		return $factory;
	}

	private function create_item_factory_from_response($raw_item): ItemFactory
	{
		$factory = Mockery::mock(ItemFactory::class);
		$factory->expects('from_paypal_response')->with($raw_item)->andReturn($this->item);
		return $factory;
	}

	private function create_payment_level_eligibility_mock(
		string $gateway_id = PayPalGateway::ID,
		bool $eligible = false
	): PaymentLevelEligibility {
		$mock = Mockery::mock(PaymentLevelEligibility::class);
		$mock->shouldReceive('is_eligible')->with($gateway_id)->andReturn($eligible);
		return $mock;
	}

	private function create_wc_order_mock(string $payment_method = PayPalGateway::ID): \WC_Order
	{
		$order = Mockery::mock(\WC_Order::class);
		$order->shouldReceive('get_order_number')->andReturn($this->wcOrderNumber);
		$order->shouldReceive('get_id')->andReturn($this->wcOrderId);
		$order->shouldReceive('get_payment_method')->andReturn($payment_method);
		return $order;
	}

	private function create_testee(
		AmountFactory $amount_factory,
		ItemFactory $item_factory,
		ShippingFactory $shipping_factory,
		?PaymentsFactory $payments_factory = null,
		?PaymentLevelHelper $payment_level_helper = null,
		?PaymentLevelEligibility $payment_level_eligibility = null,
		?SettingsProvider $settings = null
	): PurchaseUnitFactory {
		return new PurchaseUnitFactory(
			$amount_factory,
			$item_factory,
			$shipping_factory,
			$payments_factory ?? Mockery::mock(PaymentsFactory::class),
			$payment_level_helper ?? Mockery::mock(PaymentLevelHelper::class),
			$payment_level_eligibility ?? $this->create_payment_level_eligibility_mock(),
			$settings ?? Mockery::mock(SettingsProvider::class)
		);
	}
}
