<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Address;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payee;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Shipping;
use Mockery;
use function Brain\Monkey\Functions\expect;

class PurchaseUnitFactoryTest
{

    public function test() {

        $wcOrder = Mockery::mock(\WC_Order::class);
        $amountFactory = Mockery::mock(AmountFactory::class);
        $payeeFactory = Mockery::mock(Payee::class);
        $itemFactory = Mockery::mock(ItemFactory::class);

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
        $testee = new PurchaseUnitFactory($amountFactory, $payeeFactory, $itemFactory, $shippingFactory);

        $wcOrder
            ->expects('get_items')
            ->with('line_item')
            ->andReturn([]);
        $wcOrder
            ->expects('get_total')
            ->andReturn(1.00);
        $wcOrder
            ->expects('get_shipping_total')
            ->andReturn(1.00);
        $wcOrder
            ->expects('get_shipping_tax')
            ->andReturn(1.00);
        $wcOrder
            ->expects('get_total_discount')
            ->twice()
            ->with(false)
            ->andReturn(1.00);
        $currency = 'EUR';
        expect('get_woocommerce_currency')->andReturn($currency);

        $unit = $testee->fromWcOrder($wcOrder);
    }
}