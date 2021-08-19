<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class OrderTest extends TestCase
{
    private $dateFormat = 'Y-m-d\TH:i:sO'; // no DateTimeInterface::ISO8601 in 7.1

    public function testOrder()
    {
        $id = 'id';
        $createTime = new \DateTime();
        $updateTime = new \DateTime();
        $unit = Mockery::mock(PurchaseUnit::class);
        $unit->expects('to_array')->andReturn([1]);
        $status = Mockery::mock(OrderStatus::class);
        $status->expects('name')->andReturn('CREATED');
        $payer = Mockery::mock(Payer::class);
        $payer
            ->expects('to_array')->andReturn(['payer']);
        $intent = 'AUTHORIZE';
        $applicationContext = Mockery::mock(ApplicationContext::class);
        $applicationContext
            ->expects('to_array')
            ->andReturn(['applicationContext']);
        $paymentSource = Mockery::mock(PaymentSource::class);
        $paymentSource
            ->expects('to_array')
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
        $this->assertEquals($createTime, $testee->create_time());
        $this->assertEquals($updateTime, $testee->update_time());
        $this->assertEquals([$unit], $testee->purchase_units());
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
            'create_time' => $createTime->format($this->dateFormat),
            'update_time' => $updateTime->format($this->dateFormat),
            'payer' => ['payer'],
            'application_context' => ['applicationContext'],
            'payment_source' => ['paymentSource']
        ];
        $this->assertEquals($expected, $testee->to_array());
    }

    public function testOrderNoDatesOrPayer()
    {
        $id = 'id';
        $unit = Mockery::mock(PurchaseUnit::class);
        $unit->expects('to_array')->andReturn([1]);
        $status = Mockery::mock(OrderStatus::class);
        $status->expects('name')->andReturn('CREATED');

        $testee = new Order(
            $id,
            [$unit],
            $status
        );

        $this->assertEquals(null, $testee->create_time());
        $this->assertEquals(null, $testee->update_time());
        $this->assertEquals(null, $testee->payer());
        $this->assertEquals('CAPTURE', $testee->intent());

        $array = $testee->to_array();
        $this->assertFalse(array_key_exists('payer', $array));
        $this->assertFalse(array_key_exists('create_time', $array));
        $this->assertFalse(array_key_exists('update_time', $array));
    }
}
