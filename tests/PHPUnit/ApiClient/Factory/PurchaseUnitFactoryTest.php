<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payee;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

use function Brain\Monkey\Functions\expect;

class PurchaseUnitFactoryTest extends TestCase
{
	private $wcOrderId = 1;
	private $wcOrderNumber = '100000';

    public function testWcOrderDefault()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
        $wcOrder->expects('get_id')->andReturn($this->wcOrderId);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->shouldReceive('from_wc_order')
            ->with($wcOrder)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->shouldReceive('payee')->andReturn($payee);
	    $item = Mockery::mock(Item::class);
	    $item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->shouldReceive('from_wc_order')
            ->with($wcOrder)
            ->andReturn([$item]);

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
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->shouldReceive('from_wc_order')
            ->with($wcOrder)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $unit = $testee->from_wc_order($wcOrder);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals($payee, $unit->payee());
        $this->assertEquals('', $unit->description());
        $this->assertEquals('default', $unit->reference_id());
        $this->assertEquals($this->wcOrderId, $unit->custom_id());
        $this->assertEquals('', $unit->soft_descriptor());
        $this->assertEquals('WC-' . $this->wcOrderNumber, $unit->invoice_id());
        $this->assertEquals([$item], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testWcOrderShippingGetsDroppedWhenNoPostalCode()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
        $wcOrder->expects('get_id')->andReturn($this->wcOrderId);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
	    $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn([$item]);

        $address = Mockery::mock(Address::class);
        $address
            ->expects('country_code')
            ->twice()
            ->andReturn('DE');
        $address
            ->expects('postal_code')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->expects('address')
            ->times(3)
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $unit = $testee->from_wc_order($wcOrder);
        $this->assertEquals(null, $unit->shipping());
    }

    public function testWcOrderShippingGetsDroppedWhenNoCountryCode()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('get_order_number')->andReturn($this->wcOrderNumber);
        $wcOrder->expects('get_id')->andReturn($this->wcOrderId);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
	    $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_order')
            ->with($wcOrder)
            ->andReturn([$item]);

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
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $unit = $testee->from_wc_order($wcOrder);
        $this->assertEquals(null, $unit->shipping());
    }

    public function testWcCartDefault()
    {
        $wcCustomer = Mockery::mock(\WC_Customer::class);
        expect('WC')
            ->andReturn((object) ['customer' => $wcCustomer]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);

        $item = Mockery::mock(Item::class);
        $item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn([$item]);

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
            ->with($wcCustomer)
            ->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals($payee, $unit->payee());
        $this->assertEquals('', $unit->description());
        $this->assertEquals('default', $unit->reference_id());
        $this->assertEquals('', $unit->custom_id());
        $this->assertEquals('', $unit->soft_descriptor());
        $this->assertEquals('', $unit->invoice_id());
        $this->assertEquals([$item], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testWcCartShippingGetsDroppendWhenNoCustomer()
    {
        expect('WC')
            ->andReturn((object) ['customer' => null]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $item = Mockery::mock(Item::class);
        $item->shouldReceive('category')->andReturn(Item::PHYSICAL_GOODS);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn([$item]);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertNull($unit->shipping());
    }

    public function testWcCartShippingGetsDroppendWhenNoCountryCode()
    {
        expect('WC')
            ->andReturn((object) ['customer' => Mockery::mock(\WC_Customer::class)]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
	    $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('from_wc_cart')
            ->with($wcCart)
            ->andReturn([$item]);

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
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $unit = $testee->from_wc_cart($wcCart);
        $this->assertNull($unit->shipping());
    }

    public function testFromPayPalResponseDefault()
    {
        $rawItem = (object) ['items' => 1];
        $rawAmount =  (object) ['amount' => 1];
        $rawPayee =  (object) ['payee' => 1];
        $rawShipping = (object) ['shipping' => 1];
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payee = Mockery::mock(Payee::class);
        $payeeFactory->expects('from_paypal_response')->with($rawPayee)->andReturn($payee);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shipping = Mockery::mock(Shipping::class);
        $shippingFactory->expects('from_paypal_response')->with($rawShipping)->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $response = (object) [
            'reference_id' => 'default',
            'description' => 'description',
            'custom_id' => 'customId',
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'payee' => $rawPayee,
            'shipping' => $rawShipping,
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals($payee, $unit->payee());
        $this->assertEquals('description', $unit->description());
        $this->assertEquals('default', $unit->reference_id());
        $this->assertEquals('customId', $unit->custom_id());
        $this->assertEquals('softDescriptor', $unit->soft_descriptor());
        $this->assertEquals('invoiceId', $unit->invoice_id());
        $this->assertEquals([$item], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testFromPayPalResponsePayeeIsNull()
    {
        $rawItem = (object) ['items' => 1];
        $rawAmount =  (object) ['amount' => 1];
        $rawPayee =  (object) ['payee' => 1];
        $rawShipping = (object) ['shipping' => 1];
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shipping = Mockery::mock(Shipping::class);
        $shippingFactory->expects('from_paypal_response')->with($rawShipping)->andReturn($shipping);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $response = (object) [
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
        $this->assertNull($unit->payee());
    }

    public function testFromPayPalResponseShippingIsNull()
    {
        $rawItem = (object) ['items' => 1];
        $rawAmount =  (object) ['amount' => 1];
        $rawPayee =  (object) ['payee' => 1];
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payee = Mockery::mock(Payee::class);
        $payeeFactory->expects('from_paypal_response')->with($rawPayee)->andReturn($payee);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $response = (object) [
            'reference_id' => 'default',
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'payee' => $rawPayee,
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertNull($unit->shipping());
    }

    public function testFromPayPalResponseNeedsReferenceId()
    {
        $amountFactory = Mockery::mock(AmountFactory::class);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $paymentsFacory = Mockery::mock(PaymentsFactory::class);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFacory
        );

        $response = (object) [
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => '',
            'items' => [],
            'payee' => '',
            'shipping' => '',
        ];

        $this->expectException(\WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException::class);
        $testee->from_paypal_response($response);
    }

    public function testFromPayPalResponsePaymentsGetAppended()
    {
        $rawItem = (object)['items' => 1];
        $rawAmount = (object)['amount' => 1];
        $rawPayee = (object)['payee' => 1];
        $rawShipping = (object)['shipping' => 1];
        $rawPayments = (object)['payments' => 1];

        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payee = Mockery::mock(Payee::class);
        $payeeFactory->expects('from_paypal_response')->with($rawPayee)->andReturn($payee);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
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
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFactory
        );

        $response = (object)[
            'reference_id' => 'default',
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'payee' => $rawPayee,
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
        $rawPayee = (object)['payee' => 1];
        $rawShipping = (object)['shipping' => 1];
        $rawPayments = (object)['payments' => 1];

        $amountFactory = Mockery::mock(AmountFactory::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory->expects('from_paypal_response')->with($rawAmount)->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payee = Mockery::mock(Payee::class);
        $payeeFactory->expects('from_paypal_response')->with($rawPayee)->andReturn($payee);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $item = Mockery::mock(Item::class, ['category' => Item::PHYSICAL_GOODS]);
        $itemFactory->expects('from_paypal_response')->with($rawItem)->andReturn($item);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shipping = Mockery::mock(Shipping::class);
        $shippingFactory->expects('from_paypal_response')->with($rawShipping)->andReturn($shipping);

        $paymentsFactory = Mockery::mock(PaymentsFactory::class);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFactory
        );

        $response = (object)[
            'reference_id' => 'default',
            'description' => 'description',
            'customId' => 'customId',
            'invoiceId' => 'invoiceId',
            'softDescriptor' => 'softDescriptor',
            'amount' => $rawAmount,
            'items' => [$rawItem],
            'payee' => $rawPayee,
            'shipping' => $rawShipping,
        ];

        $unit = $testee->from_paypal_response($response);
        $this->assertNull($unit->payments());
    }
}
