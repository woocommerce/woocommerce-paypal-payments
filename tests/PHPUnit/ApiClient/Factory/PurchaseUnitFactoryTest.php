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

	public function testWcOrderDefault()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
        $wcOrder->expects('get_id')->andReturn($this->wcOrderId);
	    $wcOrder->shouldReceive('get_payment_method')->andReturn(PayPalGateway::ID);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->shouldReceive('from_wc_order')
            ->with($wcOrder)
            ->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->shouldReceive('from_wc_order')
            ->with($wcOrder)
            ->andReturn([$this->item]);

        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('country_code')
            ->andReturn('DE');
        $address
            ->shouldReceive('postal_code')
            ->andReturn('12345');
		$address->shouldReceive('address_line_1')->andReturn('Berlin Street');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->shouldReceive('from_wc_order')
            ->with($wcOrder)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);

	    $paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
	    $paymentLevelEligibility
		    ->shouldReceive('is_eligible')
		    ->with(PayPalGateway::ID)
		    ->andReturn(false);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        $paymentLevelEligibility
        );

        $unit = $testee->from_wc_order($wcOrder);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals('', $unit->description());
        $this->assertEquals('default', $unit->reference_id());
        $this->assertEquals($this->wcOrderId, $unit->custom_id());
        $this->assertEquals('', $unit->soft_descriptor());
        $this->assertEquals('WC-' . $this->wcOrderNumber, $unit->invoice_id());
        $this->assertEquals([$this->item], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

	public function testWcOrderWithNegativeFees()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
		$wcOrder->expects('get_id')->andReturn($this->wcOrderId);
		$wcOrder->shouldReceive('get_payment_method')->andReturn(PayPalGateway::ID);

		$amount = Mockery::mock(Amount::class);
		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($amount);

		$fee = Mockery::mock(Item::class, [
			'category' => Item::DIGITAL_GOODS,
			'unit_amount' => new Money(10.0, 'USD'),
		]);
		$discount = Mockery::mock(Item::class, [
			'unit_amount' => new Money(-5, 'USD'),
		]);

		$itemFactory = Mockery::mock(ItemFactory::class);
		$itemFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn([$this->item, $fee, $discount]);

		$address = Mockery::mock(Address::class);
		$address->shouldReceive('country_code')->andReturn('DE');
		$address->shouldReceive('postal_code')->andReturn('12345');
		$address->shouldReceive('address_line_1')->andReturn('Berlin Street');

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);
		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($shipping);
		$paymentsFacory = Mockery::mock(PaymentsFactory::class);

		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with(PayPalGateway::ID)
			->andReturn(false);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFacory,
			Mockery::mock(PaymentLevelHelper::class),
			$paymentLevelEligibility
		);

		$unit = $testee->from_wc_order($wcOrder);
		$this->assertTrue(is_a($unit, PurchaseUnit::class));
		$this->assertEquals([$this->item, $fee], $unit->items());
	}

    public function testWcOrderShippingGetsDroppedWhenNoPostalCode()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
        $wcOrder->expects('get_id')->andReturn($this->wcOrderId);
	    $wcOrder->shouldReceive('get_payment_method')->andReturn(PayPalGateway::ID);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn([$this->item]);

        $address = Mockery::mock(Address::class);
        $address
            ->expects('country_code')
            ->twice()
            ->andReturn('DE');
        $address
            ->expects('postal_code')
            ->andReturn('');
	    $address->shouldReceive('address_line_1')->andReturn('Berlin Street');

	    $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->expects('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);

	    $paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
	    $paymentLevelEligibility
		    ->shouldReceive('is_eligible')
		    ->with(PayPalGateway::ID)
		    ->andReturn(false);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        $paymentLevelEligibility
        );

        $unit = $testee->from_wc_order($wcOrder);
        $this->assertEquals(null, $unit->shipping());
    }

    public function testWcOrderShippingGetsDroppedWhenNoCountryCode()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
        $wcOrder->expects('get_id')->andReturn($this->wcOrderId);
	    $wcOrder->shouldReceive('get_payment_method')->andReturn(PayPalGateway::ID);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn([$this->item]);

        $address = Mockery::mock(Address::class);
        $address
            ->expects('country_code')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->expects('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);

	    $paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
	    $paymentLevelEligibility
		    ->shouldReceive('is_eligible')
		    ->with(PayPalGateway::ID)
		    ->andReturn(false);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        $paymentLevelEligibility
        );

        $unit = $testee->from_wc_order($wcOrder);
        $this->assertEquals(null, $unit->shipping());
    }

	public function testWcOrderShippingGetsDroppedWhenNoAddressLine1()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
		$wcOrder->expects('get_id')->andReturn($this->wcOrderId);
		$wcOrder->shouldReceive('get_payment_method')->andReturn(PayPalGateway::ID);
		$amount = Mockery::mock(Amount::class);
		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->expects('from_wc_order')
			->with($wcOrder)
			->andReturn($amount);
		$itemFactory = Mockery::mock(ItemFactory::class);
		$itemFactory
			->expects('from_wc_order')
			->with($wcOrder)
			->andReturn([$this->item]);

		$address = Mockery::mock(Address::class);
		$address
			->expects('country_code')
			->andReturn('DE');
		$address->shouldReceive('postal_code')->andReturn('12345');
		$address->shouldReceive('address_line_1')->andReturn('');

		$shipping = Mockery::mock(Shipping::class);
		$shipping
			->expects('address')
			->andReturn($address);
		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->expects('from_wc_order')
			->with($wcOrder)
			->andReturn($shipping);
		$paymentsFacory = Mockery::mock(PaymentsFactory::class);

		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with(PayPalGateway::ID)
			->andReturn(false);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFacory,
			Mockery::mock(PaymentLevelHelper::class),
			$paymentLevelEligibility
		);

		$unit = $testee->from_wc_order($wcOrder);
		$this->assertEquals(null, $unit->shipping());
	}

    public function testWcCartDefault()
    {
        $wcCustomer = Mockery::mock(\WC_Customer::class);
        expect('WC')
            ->andReturn((object) ['customer' => $wcCustomer, 'session' => null]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn($amount);

        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn([$this->item]);

        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('country_code')
            ->andReturn('DE');
        $address
            ->shouldReceive('postal_code')
            ->andReturn('12345');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->zeroOrMoreTimes()
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('from_wc_customer')
            ->with($wcCustomer, false)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);

	    $paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
	    $paymentLevelEligibility
		    ->shouldReceive('is_eligible')
		    ->with('')
		    ->andReturn(false);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        $paymentLevelEligibility
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals('', $unit->description());
        $this->assertEquals('default', $unit->reference_id());
        $this->assertEquals('', $unit->custom_id());
        $this->assertEquals('', $unit->soft_descriptor());
        $this->assertEquals('', $unit->invoice_id());
        $this->assertEquals([$this->item], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testWcCartShippingGetsDroppendWhenNoCustomer()
    {
        expect('WC')
            ->andReturn((object) ['customer' => null, 'session' => null]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn($amount);

        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn([$this->item]);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);

	    $paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
	    $paymentLevelEligibility
		    ->shouldReceive('is_eligible')
		    ->with('')
		    ->andReturn(false);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        $paymentLevelEligibility
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertNull($unit->shipping());
    }

    public function testWcCartShippingGetsDroppendWhenNoCountryCode()
    {
        expect('WC')
            ->andReturn((object) ['customer' => Mockery::mock(\WC_Customer::class), 'session' => null]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn([$this->item]);

        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('country_code')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('from_wc_customer')
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);

	    $paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
	    $paymentLevelEligibility
		    ->shouldReceive('is_eligible')
		    ->with('')
		    ->andReturn(false);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        $paymentLevelEligibility
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertNull($unit->shipping());
    }

    public function testFromPayPalResponseDefault()
    {
        $rawItem = (object) ['items' => 1];
        $rawAmount =  (object) ['amount' => 1];
        $rawShipping = (object) ['shipping' => 1];
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($this->item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shipping = Mockery::mock(Shipping::class);
        $shippingFactory->expects('from_paypal_response')->with($rawShipping)->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        Mockery::mock(PaymentLevelEligibility::class)
        );

        $response = (object) [
            'reference_id' => 'default',
            'description' => 'description',
            'custom_id' => 'customId',
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'shipping' => $rawShipping,
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals('description', $unit->description());
        $this->assertEquals('default', $unit->reference_id());
        $this->assertEquals('customId', $unit->custom_id());
        $this->assertEquals('softDescriptor', $unit->soft_descriptor());
        $this->assertEquals('invoiceId', $unit->invoice_id());
        $this->assertEquals([$this->item], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testFromPayPalResponseShippingIsNull()
    {
        $rawItem = (object) ['items' => 1];
        $rawAmount =  (object) ['amount' => 1];
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($this->item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        Mockery::mock(PaymentLevelEligibility::class)
        );

        $response = (object) [
            'reference_id' => 'default',
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertNull($unit->shipping());
    }

    public function testFromPayPalResponseNeedsReferenceId()
    {
        $amountFactory = Mockery::mock(AmountFactory::class);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory,
	        Mockery::mock(PaymentLevelHelper::class),
	        Mockery::mock(PaymentLevelEligibility::class)
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

    public function testFromPayPalResponsePaymentsGetAppended()
    {
        $rawItem = (object)['items' => 1];
        $rawAmount = (object)['amount' => 1];
        $rawShipping = (object)['shipping' => 1];
        $rawPayments = (object)['payments' => 1];

        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shipping = Mockery::mock(Shipping::class);
        $shippingFactory->expects('from_paypal_response')->with($rawShipping)->andReturn($shipping);

        $paymentsFactory = Mockery::mock(PaymentsFactory::class);
        $payments = Mockery::mock(Payments::class);
        $paymentsFactory->expects('from_paypal_response')->with($rawPayments)->andReturn($payments);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFactory,
	        Mockery::mock(PaymentLevelHelper::class),
	        Mockery::mock(PaymentLevelEligibility::class)
        );

        $response = (object)[
            'reference_id' => 'default',
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'shipping' => $rawShipping,
            'payments' => $rawPayments,
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertEquals($payments, $unit->payments());
    }

    public function testFromPayPalResponsePaymentsIsNull()
    {
        $rawItem = (object)['items' => 1];
        $rawAmount = (object)['amount' => 1];
        $rawShipping = (object)['shipping' => 1];
        $rawPayments = (object)['payments' => 1];

        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shipping = Mockery::mock(Shipping::class);
        $shippingFactory->expects('from_paypal_response')->with($rawShipping)->andReturn($shipping);

        $paymentsFactory = Mockery::mock(PaymentsFactory::class);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFactory,
	        Mockery::mock(PaymentLevelHelper::class),
	        Mockery::mock(PaymentLevelEligibility::class)
        );

        $response = (object)[
            'reference_id' => 'default',
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'shipping' => $rawShipping,
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertNull($unit->payments());
    }

	public function testWcOrderWithLevel2Processing()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
		$wcOrder->expects('get_id')->andReturn($this->wcOrderId);
		$wcOrder->shouldReceive('get_payment_method')->andReturn(CreditCardGateway::ID);

		// Mock Amount with to_array() expectation
		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '100.00'
		]);

		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($amount);

		// Mock Item with to_array() expectation
		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Test Item',
			'unit_amount' => ['currency_code' => 'USD', 'value' => '100.00'],
			'quantity' => '1'
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(100.0, 'USD'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$itemFactory = Mockery::mock(ItemFactory::class);
		$itemFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn([$item]);

		$address = Mockery::mock(Address::class);
		$address->shouldReceive('country_code')->andReturn('US');
		$address->shouldReceive('postal_code')->andReturn('12345');
		$address->shouldReceive('address_line_1')->andReturn('123 Main St');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'postal_code' => '12345',
			'address_line_1' => '123 Main St'
		]);

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '12345',
				'address_line_1' => '123 Main St'
			]
		]);

		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($shipping);

		$paymentsFactory = Mockery::mock(PaymentsFactory::class);

		// Mock Level 2 processing to return true
		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with(CreditCardGateway::ID)
			->andReturn(true);

		// Mock Level 2 data structure
		$level2Data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_12345',
						'tax_total' => [
							'currency_code' => 'USD',
							'value' => '8.50'
						]
					]
				]
			]
		];

		$paymentLevelHelper = Mockery::mock(PaymentLevelHelper::class);
		$paymentLevelHelper
			->shouldReceive('build')
			->with($amount, 'level_2')
			->andReturn($level2Data);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFactory,
			$paymentLevelHelper,
			$paymentLevelEligibility
		);

		$unit = $testee->from_wc_order($wcOrder);

		// Assert that the purchase unit was created
		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		// Assert that supplementary_data is present in the array output
		$unitArray = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unitArray);
		$this->assertArrayHasKey('card', $unitArray['supplementary_data']);
		$this->assertArrayHasKey('level_2', $unitArray['supplementary_data']['card']);
		$this->assertEquals('INV_12345', $unitArray['supplementary_data']['card']['level_2']['invoice_id']);  // Changed field name
		$this->assertEquals('USD', $unitArray['supplementary_data']['card']['level_2']['tax_total']['currency_code']);
		$this->assertEquals('8.50', $unitArray['supplementary_data']['card']['level_2']['tax_total']['value']);
	}

	public function testWcOrderWithoutLevel2ProcessingWhenNotEligible()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
		$wcOrder->expects('get_id')->andReturn($this->wcOrderId);
		$wcOrder->shouldReceive('get_payment_method')->andReturn(PayPalGateway::ID);

		// Mock Amount with to_array() expectation
		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'EUR',
			'value' => '100.00'
		]);

		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($amount);

		// Mock Item with to_array() expectation
		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Test Item',
			'unit_amount' => ['currency_code' => 'EUR', 'value' => '100.00'],
			'quantity' => '1'
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(100.0, 'EUR'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$itemFactory = Mockery::mock(ItemFactory::class);
		$itemFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn([$item]);

		$address = Mockery::mock(Address::class);
		$address->shouldReceive('country_code')->andReturn('DE');
		$address->shouldReceive('postal_code')->andReturn('12345');
		$address->shouldReceive('address_line_1')->andReturn('Berlin Street');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'DE',
			'postal_code' => '12345',
			'address_line_1' => 'Berlin Street'
		]);

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'DE',
				'postal_code' => '12345',
				'address_line_1' => 'Berlin Street'
			]
		]);

		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($shipping);

		$paymentsFactory = Mockery::mock(PaymentsFactory::class);

		// Mock Level 2 processing to return false (not eligible)
		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with(PayPalGateway::ID)
			->andReturn(false);

		$paymentLevelHelper = Mockery::mock(PaymentLevelHelper::class);
		// build() should NOT be called when not eligible
		$paymentLevelHelper->shouldNotReceive('build');

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFactory,
			$paymentLevelHelper,
			$paymentLevelEligibility
		);

		$unit = $testee->from_wc_order($wcOrder);

		// Assert that the purchase unit was created
		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		// Assert that supplementary_data is NOT present
		$unitArray = $unit->to_array();
		$this->assertArrayNotHasKey('supplementary_data', $unitArray);
	}
}
