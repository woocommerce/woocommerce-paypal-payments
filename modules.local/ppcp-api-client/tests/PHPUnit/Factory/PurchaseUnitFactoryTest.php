<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Address;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Amount;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payee;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Shipping;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use function Brain\Monkey\Functions\expect;
use Mockery;

class PurchaseUnitFactoryTest extends TestCase
{

    public function testWcOrder() {

        $wcOrder = Mockery::mock(\WC_Order::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->shouldReceive('fromWcOrder')
            ->with($wcOrder)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->shouldReceive('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->shouldReceive('fromWcOrder')
            ->with($wcOrder)
            ->andReturn([]);

        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('countryCode')
            ->twice()
            ->andReturn('DE');
        $address
            ->shouldReceive('postalCode')
            ->andReturn('12345');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->shouldReceive('fromWcOrder')
            ->with($wcOrder)
            ->andReturn($shipping);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcOrder($wcOrder);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals($payee, $unit->payee());
        $this->assertEquals('', $unit->description());
        $this->assertEquals('default', $unit->referenceId());
        $this->assertEquals('', $unit->customId());
        $this->assertEquals('', $unit->softDescriptor());
        $this->assertEquals('', $unit->invoiceId());
        $this->assertEquals([], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testWcOrderShippingGetsDroppedWhenNoPostalCode() {

        $wcOrder = Mockery::mock(\WC_Order::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('fromWcOrder')
            ->with($wcOrder)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('fromWcOrder')
            ->with($wcOrder)
            ->andReturn([]);

        $address = Mockery::mock(Address::class);
        $address
            ->expects('countryCode')
            ->twice()
            ->andReturn('DE');
        $address
            ->expects('postalCode')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->expects('address')
            ->times(3)
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('fromWcOrder')
            ->with($wcOrder)
            ->andReturn($shipping);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcOrder($wcOrder);
        $this->assertEquals(null, $unit->shipping());
    }

    public function testWcOrderShippingGetsDroppedWhenNoCountryCode() {

        $wcOrder = Mockery::mock(\WC_Order::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('fromWcOrder')
            ->with($wcOrder)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('fromWcOrder')
            ->with($wcOrder)
            ->andReturn([]);

        $address = Mockery::mock(Address::class);
        $address
            ->expects('countryCode')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->expects('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('fromWcOrder')
            ->with($wcOrder)
            ->andReturn($shipping);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcOrder($wcOrder);
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
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn([]);

        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('countryCode')
            ->andReturn('DE');
        $address
            ->shouldReceive('postalCode')
            ->andReturn('12345');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->zeroOrMoreTimes()
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('fromWcCustomer')
            ->with($wcCustomer)
            ->andReturn($shipping);
        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcCart($wcCart);
        $this->assertTrue(is_a($unit, PurchaseUnit::class));
        $this->assertEquals($payee, $unit->payee());
        $this->assertEquals('', $unit->description());
        $this->assertEquals('default', $unit->referenceId());
        $this->assertEquals('', $unit->customId());
        $this->assertEquals('', $unit->softDescriptor());
        $this->assertEquals('', $unit->invoiceId());
        $this->assertEquals([], $unit->items());
        $this->assertEquals($amount, $unit->amount());
        $this->assertEquals($shipping, $unit->shipping());
    }

    public function testWcCartShippingGetsDroppendWhenNoCustomer() {

        expect('WC')
            ->andReturn((object) ['customer' => null]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn([]);
        $shippingFactory = Mockery::mock(ShippingFactory::class);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcCart($wcCart);
        $this->assertNull($unit->shipping());
    }
    public function testWcCartShippingGetsDroppendWhenNoPostalCode() {

        expect('WC')
            ->andReturn((object) ['customer' => Mockery::mock(\WC_Customer::class)]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn([]);


        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('countryCode')
            ->andReturn('DE');
        $address
            ->shouldReceive('postalCode')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('fromWcCustomer')
            ->andReturn($shipping);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcCart($wcCart);
        $this->assertNull($unit->shipping());
    }
    public function testWcCartShippingGetsDroppendWhenNoCountryCode() {

        expect('WC')
            ->andReturn((object) ['customer' => Mockery::mock(\WC_Customer::class)]);

        $wcCart = Mockery::mock(\WC_Cart::class);
        $amount = Mockery::mock(Amount::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $amountFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn($amount);
        $payeeFactory = Mockery::mock(PayeeFactory::class);
        $payeeRepository = Mockery::mock(PayeeRepository::class);
        $payee = Mockery::mock(Payee::class);
        $payeeRepository
            ->expects('payee')->andReturn($payee);
        $itemFactory = Mockery::mock(ItemFactory::class);
        $itemFactory
            ->expects('fromWcCart')
            ->with($wcCart)
            ->andReturn([]);


        $address = Mockery::mock(Address::class);
        $address
            ->shouldReceive('countryCode')
            ->andReturn('');
        $shipping = Mockery::mock(Shipping::class);
        $shipping
            ->shouldReceive('address')
            ->andReturn($address);
        $shippingFactory = Mockery::mock(ShippingFactory::class);
        $shippingFactory
            ->expects('fromWcCustomer')
            ->andReturn($shipping);

        $testee = new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory
        );

        $unit = $testee->fromWcCart($wcCart);
        $this->assertNull($unit->shipping());
    }

}