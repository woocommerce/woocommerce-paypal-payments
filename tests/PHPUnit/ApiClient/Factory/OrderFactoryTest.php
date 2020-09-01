<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentSource;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class OrderFactoryTest extends TestCase
{

    public function testFromWcOrder()
    {
        $createTime = new \DateTime();
        $updateTime = new \DateTime();
        $payer = Mockery::mock(Payer::class);
        $status = Mockery::mock(OrderStatus::class);
        $order = Mockery::mock(Order::class);
        $order->expects('id')->andReturn('id');
        $order->expects('status')->andReturn($status);
        $order->expects('payer')->andReturn($payer);
        $order->expects('intent')->andReturn('intent');
        $order->expects('create_time')->andReturn($createTime);
        $order->expects('update_time')->andReturn($updateTime);
        $order->expects('application_context')->andReturnNull();
        $order->expects('payment_source')->andReturnNull();
        $wcOrder = Mockery::mock(\WC_Order::class);
        $purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnitFactory->expects('from_wc_order')->with($wcOrder)->andReturn($purchaseUnit);
        $payerFactory = Mockery::mock(PayerFactory::class);
        $applicationRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationFactory = Mockery::mock(ApplicationContextFactory::class);
        $paymentSourceFactory = Mockery::mock(PaymentSourceFactory::class);


        $testee = new OrderFactory(
            $purchaseUnitFactory,
            $payerFactory,
            $applicationRepository,
            $applicationFactory,
            $paymentSourceFactory
        );
        $result = $testee->from_wc_order($wcOrder, $order);
        $resultPurchaseUnit = current($result->purchase_units());
        $this->assertEquals($purchaseUnit, $resultPurchaseUnit);
    }

    /**
     * @dataProvider dataForTestFromPayPalResponseTest
     * @param $orderData
     */
    public function testFromPayPalResponse($orderData)
    {
        $purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
        if (count($orderData->purchase_units)) {
            $purchaseUnitFactory
                ->expects('from_paypal_response')
                ->times(count($orderData->purchase_units))
                ->andReturn(Mockery::mock(PurchaseUnit::class));
        }
        $payerFactory = Mockery::mock(PayerFactory::class);
        if (isset($orderData->payer)) {
            $payerFactory
                ->expects('from_paypal_response')
                ->andReturn(Mockery::mock(Payer::class));
        }
        $applicationRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationFactory = Mockery::mock(ApplicationContextFactory::class);
        $paymentSourceFactory = Mockery::mock(PaymentSourceFactory::class);

        $testee = new OrderFactory(
            $purchaseUnitFactory,
            $payerFactory,
            $applicationRepository,
            $applicationFactory,
            $paymentSourceFactory
        );
        $order = $testee->from_paypal_response($orderData);

        $this->assertCount(count($orderData->purchase_units), $order->purchase_units());
        $this->assertEquals($orderData->id, $order->id());
        $this->assertEquals($orderData->status, $order->status()->name());
        $this->assertEquals($orderData->intent, $order->intent());
        if (! isset($orderData->create_time)) {
            $this->assertNull($order->create_time());
        } else {
            $this->assertEquals($orderData->create_time, $order->create_time()->format(\DateTime::ISO8601));
        }
        if (! isset($orderData->payer)) {
            $this->assertNull($order->payer());
        } else {
            $this->assertInstanceOf(Payer::class, $order->payer());
        }
        if (! isset($orderData->update_time)) {
            $this->assertNull($order->update_time());
        } else {
            $this->assertEquals($orderData->update_time, $order->update_time()->format(\DateTime::ISO8601));
        }
    }

    public function dataForTestFromPayPalResponseTest() : array
    {
        return [
            'default' => [
                (object) [
                    'id' => 'id',
                    'purchase_units' => [new \stdClass(), new \stdClass()],
                    'status' => OrderStatus::APPROVED,
                    'intent' => 'CAPTURE',
                    'create_time' => '2005-08-15T15:52:01+0000',
                    'update_time' => '2005-09-15T15:52:01+0000',
                    'payer' => new \stdClass(),
                ],
            ],
            'no_update_time' => [
                (object) [
                    'id' => 'id',
                    'purchase_units' => [new \stdClass(), new \stdClass()],
                    'status' => OrderStatus::APPROVED,
                    'intent' => 'CAPTURE',
                    'create_time' => '2005-08-15T15:52:01+0000',
                    'payer' => new \stdClass(),
                ],
            ],
            'no_create_time' => [
                (object) [
                    'id' => 'id',
                    'purchase_units' => [new \stdClass(), new \stdClass()],
                    'status' => OrderStatus::APPROVED,
                    'intent' => 'CAPTURE',
                    'update_time' => '2005-09-15T15:52:01+0000',
                    'payer' => new \stdClass(),
                ],
            ],
            'no_payer' => [
                (object) [
                    'id' => 'id',
                    'purchase_units' => [new \stdClass(), new \stdClass()],
                    'status' => OrderStatus::APPROVED,
                    'intent' => 'CAPTURE',
                    'create_time' => '2005-08-15T15:52:01+0000',
                    'update_time' => '2005-09-15T15:52:01+0000',
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataForTestFromPayPalResponseExceptionsTest
     * @param $orderData
     */
    public function testFromPayPalResponseExceptions($orderData)
    {
        $purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
        $payerFactory = Mockery::mock(PayerFactory::class);
        $applicationRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationFactory = Mockery::mock(ApplicationContextFactory::class);
        $paymentSourceFactory = Mockery::mock(PaymentSourceFactory::class);

        $testee = new OrderFactory(
            $purchaseUnitFactory,
            $payerFactory,
            $applicationRepository,
            $applicationFactory,
            $paymentSourceFactory
        );

        $this->expectException(RuntimeException::class);
        $testee->from_paypal_response($orderData);
    }

    public function dataForTestFromPayPalResponseExceptionsTest() : array
    {
        return [
            'no_id' => [
                (object) [
                    'purchase_units' => [],
                    'status' => '',
                    'intent' => '',
                ],
            ],
            'no_purchase_units' => [
                (object) [
                    'id' => '',
                    'status' => '',
                    'intent' => '',
                ],
            ],
            'purchase_units_is_not_array' => [
                (object) [
                    'id' => '',
                    'purchase_units' => 1,
                    'status' => '',
                    'intent' => '',
                ],
            ],
            'no_status' => [
                (object) [
                    'id' => '',
                    'purchase_units' => [],
                    'intent' => '',
                ],
            ],
            'no_intent' => [
                (object) [
                    'id' => '',
                    'purchase_units' => [],
                    'status' => '',
                ],
            ],
        ];
    }
}
