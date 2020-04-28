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
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Mockery;
class AuthorizedPaymentsProcessorTest extends TestCase
{

    public function testDefault() {
        $orderId = 'abc';
        $authorizationId = 'def';
        $authorizationStatus = Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::CAPTURED)
            ->andReturn(false);
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
            ->with(WcGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $result = $testee->process($wcOrder);
        $this->assertEquals(AuthorizedPaymentsProcessor::SUCCESSFUL, $result);
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
            ->with(WcGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $result = $testee->process($wcOrder);
        $this->assertEquals(AuthorizedPaymentsProcessor::INACCESSIBLE, $result);
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
            ->with(WcGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $result = $testee->process($wcOrder);
        $this->assertEquals(AuthorizedPaymentsProcessor::NOT_FOUND, $result);
    }

    public function testCaptureFails() {
        $orderId = 'abc';
        $authorizationId = 'def';
        $authorizationStatus = Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::CAPTURED)
            ->andReturn(false);
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
            ->with(WcGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $result = $testee->process($wcOrder);
        $this->assertEquals(AuthorizedPaymentsProcessor::FAILED, $result);
    }

    public function testAllAreCaptured() {
        $orderId = 'abc';
        $authorizationId = 'def';
        $authorizationStatus = Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus
            ->shouldReceive('is')
            ->with(AuthorizationStatus::CAPTURED)
            ->andReturn(true);
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
        $testee = new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder
            ->expects('get_meta')
            ->with(WcGateway::ORDER_ID_META_KEY)
            ->andReturn($orderId);
        $result = $testee->process($wcOrder);
        $this->assertEquals(AuthorizedPaymentsProcessor::ALREADY_CAPTURED, $result);
    }
}