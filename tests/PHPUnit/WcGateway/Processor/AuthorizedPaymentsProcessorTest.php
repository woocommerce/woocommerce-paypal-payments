<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;


use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payments;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\TestCase;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Mockery;
class AuthorizedPaymentsProcessorTest extends TestCase
{

    public function testDefault() {
        $orderId = 'abc';
        $authorizationId = 'def';
        $authorizationStatus = Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::CREATED)
            ->andReturn(true);
        $authorization = Mockery::mock(Authorization::class);
        $authorization
            ->shouldReceive('id')
            ->andReturn($authorizationId);
        $authorization
            ->shouldReceive('status')
            ->andReturn($authorizationStatus);
        $payments = Mockery::mock(Payments::class);
        $payments
            ->expects('authorizations')
            ->andReturn([$authorization]);
        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnit
            ->expects('payments')
            ->andReturn($payments);
        $order = Mockery::mock(Order::class);
        $order
            ->expects('purchaseUnits')
            ->andReturn([$purchaseUnit]);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('order')
            ->with($orderId)
            ->andReturn($order);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $paymentsEndpoint
            ->expects('capture')
            ->with($authorizationId)
            ->andReturn($authorization);
        $testee = new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_meta')
            ->with(PayPalGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $this->assertTrue($testee->process($wcOrder));
        $this->assertEquals(AuthorizedPaymentsProcessor::SUCCESSFUL, $testee->lastStatus());
    }

    public function testInaccessible() {
        $orderId = 'abc';
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('order')
            ->with($orderId)
            ->andThrow(RuntimeException::class);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $testee = new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_meta')
            ->with(PayPalGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $this->assertFalse($testee->process($wcOrder));
        $this->assertEquals(AuthorizedPaymentsProcessor::INACCESSIBLE, $testee->lastStatus());
    }

    public function testNotFound() {
        $orderId = 'abc';
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('order')
            ->with($orderId)
            ->andThrow(new RuntimeException("text", 404));
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $testee = new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_meta')
            ->with(PayPalGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $this->assertFalse($testee->process($wcOrder));
        $this->assertEquals(AuthorizedPaymentsProcessor::NOT_FOUND, $testee->lastStatus());
    }

    public function testCaptureFails() {
        $orderId = 'abc';
        $authorizationId = 'def';
        $authorizationStatus = Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::CREATED)
            ->andReturn(true);
        $authorization = Mockery::mock(Authorization::class);
        $authorization
            ->shouldReceive('id')
            ->andReturn($authorizationId);
        $authorization
            ->shouldReceive('status')
            ->andReturn($authorizationStatus);
        $payments = Mockery::mock(Payments::class);
        $payments
            ->expects('authorizations')
            ->andReturn([$authorization]);
        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnit
            ->expects('payments')
            ->andReturn($payments);
        $order = Mockery::mock(Order::class);
        $order
            ->expects('purchaseUnits')
            ->andReturn([$purchaseUnit]);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('order')
            ->with($orderId)
            ->andReturn($order);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $paymentsEndpoint
            ->expects('capture')
            ->with($authorizationId)
            ->andThrow(RuntimeException::class);
        $testee = new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_meta')
            ->with(PayPalGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $this->assertFalse($testee->process($wcOrder));
        $this->assertEquals(AuthorizedPaymentsProcessor::FAILED, $testee->lastStatus());
    }

    public function testAllAreCaptured() {
        $orderId = 'abc';
        $authorizationId = 'def';
        $authorizationStatus = Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::CREATED)
            ->andReturn(false);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::PENDING)
            ->andReturn(false);
        $authorization = Mockery::mock(Authorization::class);
        $authorization
            ->shouldReceive('id')
            ->andReturn($authorizationId);
        $authorization
            ->shouldReceive('status')
            ->andReturn($authorizationStatus);
        $payments = Mockery::mock(Payments::class);
        $payments
            ->expects('authorizations')
            ->andReturn([$authorization]);
        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnit
            ->expects('payments')
            ->andReturn($payments);
        $order = Mockery::mock(Order::class);
        $order
            ->expects('purchaseUnits')
            ->andReturn([$purchaseUnit]);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('order')
            ->with($orderId)
            ->andReturn($order);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $testee = new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_meta')
            ->with(PayPalGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $this->assertFalse($testee->process($wcOrder));
        $this->assertEquals(AuthorizedPaymentsProcessor::ALREADY_CAPTURED, $testee->lastStatus());
    }
}