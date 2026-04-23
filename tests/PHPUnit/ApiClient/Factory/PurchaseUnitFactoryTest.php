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
	        $paymentLevelEligibility,
	        Mockery::mock(SettingsProvider::class)
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
			$paymentLevelEligibility,
			Mockery::mock(SettingsProvider::class)
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
	        $paymentLevelEligibility,
	        Mockery::mock(SettingsProvider::class)
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
	        $paymentLevelEligibility,
	        Mockery::mock(SettingsProvider::class)
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
			$paymentLevelEligibility,
			Mockery::mock(SettingsProvider::class)
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
	        $paymentLevelEligibility,
	        Mockery::mock(SettingsProvider::class)
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

    public function testWcCartShippingGetsDroppedWhenNoCustomer()
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
	        $paymentLevelEligibility,
	        Mockery::mock(SettingsProvider::class)
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertNull($unit->shipping());
    }

    public function testWcCartShippingGetsDroppedWhenNoCountryCode()
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
	        $paymentLevelEligibility,
	        Mockery::mock(SettingsProvider::class)
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
	        Mockery::mock(PaymentLevelEligibility::class),
	        Mockery::mock(SettingsProvider::class)
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
	        Mockery::mock(PaymentLevelEligibility::class),
	        Mockery::mock(SettingsProvider::class)
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
	        Mockery::mock(PaymentLevelEligibility::class),
	        Mockery::mock(SettingsProvider::class)
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
	        Mockery::mock(PaymentLevelEligibility::class),
	        Mockery::mock(SettingsProvider::class)
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
	        Mockery::mock(PaymentLevelEligibility::class),
	        Mockery::mock(SettingsProvider::class)
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
			->with($amount, [$item], $shipping)
			->andReturn($level2Data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings
			->shouldReceive('is_payment_level_processing_enabled')
			->andReturn(true);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFactory,
			$paymentLevelHelper,
			$paymentLevelEligibility,
			$settings
		);

		$unit = $testee->from_wc_order($wcOrder);

		// Assert that the purchase unit was created
		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		// Assert that supplementary_data is present in the array output
		$unitArray = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unitArray);
		$this->assertArrayHasKey('card', $unitArray['supplementary_data']);
		$this->assertArrayHasKey('level_2', $unitArray['supplementary_data']['card']);
		$this->assertEquals('INV_12345', $unitArray['supplementary_data']['card']['level_2']['invoice_id']);
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
			$paymentLevelEligibility,
			Mockery::mock(SettingsProvider::class)
		);

		$unit = $testee->from_wc_order($wcOrder);

		// Assert that the purchase unit was created
		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		// Assert that supplementary_data is NOT present
		$unitArray = $unit->to_array();
		$this->assertArrayNotHasKey('supplementary_data', $unitArray);
	}

	public function testWcOrderWithLevel3Processing()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
		$wcOrder->expects('get_id')->andReturn($this->wcOrderId);
		$wcOrder->shouldReceive('get_payment_method')->andReturn(CreditCardGateway::ID);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '115.00'
		]);

		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($amount);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Test Product',
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
		$address->shouldReceive('postal_code')->andReturn('94102');
		$address->shouldReceive('address_line_1')->andReturn('123 Market St');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'postal_code' => '94102',
			'address_line_1' => '123 Market St'
		]);

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '94102',
				'address_line_1' => '123 Market St'
			]
		]);

		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($shipping);

		$paymentsFactory = Mockery::mock(PaymentsFactory::class);

		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with(CreditCardGateway::ID)
			->andReturn(true);

		// Mock Level 3 data structure
		$level3Data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_12345'
					],
					'level_3' => [
						'shipping_amount' => [
							'currency_code' => 'USD',
							'value' => '10.00'
						],
						'discount_amount' => [
							'currency_code' => 'USD',
							'value' => '5.00'
						],
						'shipping_address' => [
							'country_code' => 'US',
							'postal_code' => '94102',
							'address_line_1' => '123 Market St'
						],
						'ships_from_postal_code' => '12345',
						'line_items' => [
							[
								'name' => 'Test Product',
								'quantity' => '1',
								'unit_amount' => [
									'currency_code' => 'USD',
									'value' => '100.00'
								],
								'total_amount' => [
									'currency_code' => 'USD',
									'value' => '100.00'
								],
								'commodity_code' => 'SKU-123',
								'unit_of_measure' => 'POUND_GB_US'
							]
						]
					]
				]
			]
		];

		$paymentLevelHelper = Mockery::mock(PaymentLevelHelper::class);
		$paymentLevelHelper
			->shouldReceive('build')
			->with($amount, [$item], $shipping)
			->andReturn($level3Data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings
			->shouldReceive('is_payment_level_processing_enabled')
			->andReturn(true);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFactory,
			$paymentLevelHelper,
			$paymentLevelEligibility,
			$settings
		);

		$unit = $testee->from_wc_order($wcOrder);

		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		$unitArray = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unitArray);
		$this->assertArrayHasKey('card', $unitArray['supplementary_data']);

		// Verify Level 3 data is present
		$this->assertArrayHasKey('level_3', $unitArray['supplementary_data']['card']);
		$level3 = $unitArray['supplementary_data']['card']['level_3'];

		$this->assertArrayHasKey('shipping_amount', $level3);
		$this->assertEquals('10.00', $level3['shipping_amount']['value']);

		$this->assertArrayHasKey('discount_amount', $level3);
		$this->assertEquals('5.00', $level3['discount_amount']['value']);

		$this->assertArrayHasKey('shipping_address', $level3);
		$this->assertEquals('US', $level3['shipping_address']['country_code']);

		$this->assertArrayHasKey('ships_from_postal_code', $level3);
		$this->assertEquals('12345', $level3['ships_from_postal_code']);

		$this->assertArrayHasKey('line_items', $level3);
		$this->assertCount(1, $level3['line_items']);
		$this->assertEquals('Test Product', $level3['line_items'][0]['name']);
		$this->assertEquals('SKU-123', $level3['line_items'][0]['commodity_code']);
	}

	public function testWcOrderWithBothLevel2AndLevel3Processing()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
		$wcOrder->expects('get_id')->andReturn($this->wcOrderId);
		$wcOrder->shouldReceive('get_payment_method')->andReturn(CreditCardGateway::ID);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '118.50'
		]);

		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($amount);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Premium Widget',
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
		$address->shouldReceive('postal_code')->andReturn('10001');
		$address->shouldReceive('address_line_1')->andReturn('350 5th Ave');
		$address->shouldReceive('to_array')->andReturn([
			'country_code' => 'US',
			'postal_code' => '10001',
			'address_line_1' => '350 5th Ave'
		]);

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '10001',
				'address_line_1' => '350 5th Ave'
			]
		]);

		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($shipping);

		$paymentsFactory = Mockery::mock(PaymentsFactory::class);

		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with(CreditCardGateway::ID)
			->andReturn(true);

		// Mock combined Level 2 + Level 3 data structure
		$combinedData = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_COMBINED_789',
						'tax_total' => [
							'currency_code' => 'USD',
							'value' => '8.50'
						]
					],
					'level_3' => [
						'shipping_amount' => [
							'currency_code' => 'USD',
							'value' => '10.00'
						],
						'discount_amount' => [
							'currency_code' => 'USD',
							'value' => '0.00'
						],
						'duty_amount' => [
							'currency_code' => 'USD',
							'value' => '2.50'
						],
						'shipping_address' => [
							'country_code' => 'US',
							'postal_code' => '10001',
							'address_line_1' => '350 5th Ave'
						],
						'ships_from_postal_code' => '94102',
						'line_items' => [
							[
								'name' => 'Premium Widget',
								'quantity' => '1',
								'unit_amount' => [
									'currency_code' => 'USD',
									'value' => '100.00'
								],
								'total_amount' => [
									'currency_code' => 'USD',
									'value' => '100.00'
								],
								'description' => 'High quality widget',
								'commodity_code' => 'WIDGET-001',
								'upc' => [
									'type' => 'UPC-A',
									'code' => '012345678905'
								],
								'tax' => [
									'currency_code' => 'USD',
									'value' => '8.50'
								],
								'unit_of_measure' => 'KILOGRAM'
							]
						]
					]
				]
			]
		];

		$paymentLevelHelper = Mockery::mock(PaymentLevelHelper::class);
		$paymentLevelHelper
			->shouldReceive('build')
			->with($amount, [$item], $shipping)
			->andReturn($combinedData);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings
			->shouldReceive('is_payment_level_processing_enabled')
			->andReturn(true);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFactory,
			$paymentLevelHelper,
			$paymentLevelEligibility,
			$settings
		);

		$unit = $testee->from_wc_order($wcOrder);

		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		$unitArray = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unitArray);
		$this->assertArrayHasKey('card', $unitArray['supplementary_data']);

		// Verify both Level 2 and Level 3 are present
		$this->assertArrayHasKey('level_2', $unitArray['supplementary_data']['card']);
		$this->assertArrayHasKey('level_3', $unitArray['supplementary_data']['card']);

		// Verify Level 2 data
		$level2 = $unitArray['supplementary_data']['card']['level_2'];
		$this->assertEquals('INV_COMBINED_789', $level2['invoice_id']);
		$this->assertEquals('USD', $level2['tax_total']['currency_code']);
		$this->assertEquals('8.50', $level2['tax_total']['value']);

		// Verify Level 3 data
		$level3 = $unitArray['supplementary_data']['card']['level_3'];
		$this->assertEquals('10.00', $level3['shipping_amount']['value']);
		$this->assertEquals('2.50', $level3['duty_amount']['value']);
		$this->assertEquals('94102', $level3['ships_from_postal_code']);

		// Verify line items with all fields
		$this->assertCount(1, $level3['line_items']);
		$lineItem = $level3['line_items'][0];
		$this->assertEquals('Premium Widget', $lineItem['name']);
		$this->assertEquals('WIDGET-001', $lineItem['commodity_code']);
		$this->assertArrayHasKey('upc', $lineItem);
		$this->assertEquals('UPC-A', $lineItem['upc']['type']);
		$this->assertEquals('012345678905', $lineItem['upc']['code']);
		$this->assertEquals('8.50', $lineItem['tax']['value']);
		$this->assertEquals('KILOGRAM', $lineItem['unit_of_measure']);
	}

	public function testWcCartWithLevel2Processing()
	{
		$wcCustomer = Mockery::mock(\WC_Customer::class);
		expect('WC')
			->andReturn((object) ['customer' => $wcCustomer, 'session' => null]);

		$wcCart = Mockery::mock(\WC_Cart::class);

		$amount = Mockery::mock(Amount::class);
		$amount->shouldReceive('to_array')->andReturn([
			'currency_code' => 'USD',
			'value' => '58.50'
		]);

		$amountFactory = Mockery::mock(AmountFactory::class);
		$amountFactory
			->expects('from_wc_cart')
			->with($wcCart)
			->andReturn($amount);

		$item = Mockery::mock(Item::class);
		$item->shouldReceive('to_array')->andReturn([
			'name' => 'Cart Item',
			'unit_amount' => ['currency_code' => 'USD', 'value' => '50.00'],
			'quantity' => '1'
		]);
		$item->shouldReceive('unit_amount')->andReturn(new Money(50.0, 'USD'));
		$item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);

		$itemFactory = Mockery::mock(ItemFactory::class);
		$itemFactory
			->expects('from_wc_cart')
			->with($wcCart)
			->andReturn([$item]);

		$address = Mockery::mock(Address::class);
		$address->shouldReceive('country_code')->andReturn('US');
		$address->shouldReceive('postal_code')->andReturn('90210');

		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn($address);
		$shipping->shouldReceive('to_array')->andReturn([
			'address' => [
				'country_code' => 'US',
				'postal_code' => '90210'
			]
		]);

		$shippingFactory = Mockery::mock(ShippingFactory::class);
		$shippingFactory
			->expects('from_wc_customer')
			->with($wcCustomer, false)
			->andReturn($shipping);

		$paymentsFactory = Mockery::mock(PaymentsFactory::class);

		$paymentLevelEligibility = Mockery::mock(PaymentLevelEligibility::class);
		$paymentLevelEligibility
			->shouldReceive('is_eligible')
			->with('')
			->andReturn(true);

		$level2Data = [
			'supplementary_data' => [
				'card' => [
					'level_2' => [
						'invoice_id' => 'INV_CART_456',
						'tax_total' => [
							'currency_code' => 'USD',
							'value' => '4.50'
						]
					]
				]
			]
		];

		$paymentLevelHelper = Mockery::mock(PaymentLevelHelper::class);
		$paymentLevelHelper
			->shouldReceive('build')
			->with($amount, [$item], $shipping)
			->andReturn($level2Data);

		$settings = Mockery::mock(SettingsProvider::class);
		$settings
			->shouldReceive('is_payment_level_processing_enabled')
			->andReturn(true);

		$testee = new PurchaseUnitFactory(
			$amountFactory,
			$itemFactory,
			$shippingFactory,
			$paymentsFactory,
			$paymentLevelHelper,
			$paymentLevelEligibility,
			$settings
		);

		$unit = $testee->from_wc_cart($wcCart);

		$this->assertTrue(is_a($unit, PurchaseUnit::class));

		$unitArray = $unit->to_array();
		$this->assertArrayHasKey('supplementary_data', $unitArray);
		$this->assertArrayHasKey('card', $unitArray['supplementary_data']);
		$this->assertArrayHasKey('level_2', $unitArray['supplementary_data']['card']);

		$level2 = $unitArray['supplementary_data']['card']['level_2'];
		$this->assertEquals('INV_CART_456', $level2['invoice_id']);
		$this->assertEquals('4.50', $level2['tax_total']['value']);
	}
}
