<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentSource;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class OrderTest extends TestCase
{

    public function testOrder()
    {
        $id = 'id';
        $createTime = new \DateTime();
        $updateTime = new \DateTime();
        $unit = Mockery::mock(PurchaseUnit::class);
        $unit->expects('toArray')->andReturn([1]);
        $status = Mockery::mock(OrderStatus::class);
        $status->expects('name')->andReturn('CREATED');
        $payer = Mockery::mock(Payer::class);
        $payer
            ->expects('toArray')->andReturn(['payer']);
        $intent = 'AUTHORIZE';
        $applicationContext = Mockery::mock(ApplicationContext::class);
        $applicationContext
            ->expects('toArray')
            ->andReturn(['applicationContext']);
        $paymentSource = Mockery::mock(PaymentSource::class);
        $paymentSource
            ->expects('toArray')
            ->andReturn(['paymentSource']);

        $testee = new Order(
            $id,
            [$unit],
            $status,
            $applicationContext,
            $paymentSource,
            $payer,
            $intent,
            $createTime,
            $updateTime
        );

        $this->assertEquals($id, $testee->id());
        $this->assertEquals($createTime, $testee->createTime());
        $this->assertEquals($updateTime, $testee->updateTime());
        $this->assertEquals([$unit], $testee->purchaseUnits());
        $this->assertEquals($payer, $testee->payer());
        $this->assertEquals($intent, $testee->intent());
        $this->assertEquals($status, $testee->status());

        $expected = [
            'id' => $id,
            'intent' => $intent,
            'status' => 'CREATED',
            'purchase_units' => [
                [1],
            ],
            'create_time' => $createTime->format(\DateTimeInterface::ISO8601),
            'update_time' => $updateTime->format(\DateTimeInterface::ISO8601),
            'payer' => ['payer'],
            'application_context' => ['applicationContext'],
            'payment_source' => ['paymentSource']
        ];
        $this->assertEquals($expected, $testee->toArray());
    }

    public function testOrderNoDatesOrPayer()
    {
        $id = 'id';
        $unit = Mockery::mock(PurchaseUnit::class);
        $unit->expects('toArray')->andReturn([1]);
        $status = Mockery::mock(OrderStatus::class);
        $status->expects('name')->andReturn('CREATED');

        $testee = new Order(
            $id,
            [$unit],
            $status
        );

        $this->assertEquals(null, $testee->createTime());
        $this->assertEquals(null, $testee->updateTime());
        $this->assertEquals(null, $testee->payer());
        $this->assertEquals('CAPTURE', $testee->intent());

        $array = $testee->toArray();
        $this->assertFalse(array_key_exists('payer', $array));
        $this->assertFalse(array_key_exists('create_time', $array));
        $this->assertFalse(array_key_exists('update_time', $array));
    }
}
