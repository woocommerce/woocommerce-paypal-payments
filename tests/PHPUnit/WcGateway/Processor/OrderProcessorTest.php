<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;


use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Button\Helper\ThreeDSecure;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\TestCase;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\Woocommerce\Logging\WoocommerceLoggingModule;
use Mockery;

class OrderProcessorTest extends TestCase
{

    public function testAuthorize() {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus
            ->expects('is')
            ->with(OrderStatus::APPROVED)
            ->andReturn(true);
        $orderStatus
            ->expects('is')
            ->with(OrderStatus::COMPLETED)
            ->andReturn(true);
        $orderId = 'abc';
        $orderIntent = 'AUTHORIZE';
        $currentOrder = Mockery::mock(Order::class);
        $currentOrder
            ->expects('id')
            ->andReturn($orderId);
        $currentOrder
            ->shouldReceive('intent')
            ->andReturn($orderIntent);
        $currentOrder
            ->shouldReceive('status')
            ->andReturn($orderStatus);
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
            ->expects('order')
            ->andReturn($currentOrder);
        $sessionHandler
            ->expects('destroySessionData');
        $cartRepository = Mockery::mock(CartRepository::class);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('patchOrderWith')
            ->with($currentOrder, $currentOrder)
            ->andReturn($currentOrder);
        $orderEndpoint
            ->expects('authorize')
            ->with($currentOrder)
            ->andReturn($currentOrder);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $orderFactory
            ->expects('fromWcOrder')
            ->with($wcOrder, $currentOrder)
            ->andReturn($currentOrder);
        $threeDSecure = Mockery::mock(ThreeDSecure::class);
        $authorizedPaymentProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')
            ->andReturnFalse();

        $testee = new OrderProcessor(
            $sessionHandler,
            $cartRepository,
            $orderEndpoint,
            $paymentsEndpoint,
            $orderFactory,
            $threeDSecure,
            $authorizedPaymentProcessor,
            $settings
        );

        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('empty_cart');
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $woocommerce->cart = $cart;

        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::ORDER_ID_META_KEY,
                $orderId
            );
        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::CAPTURED_META_KEY,
                'false'
            );
        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::INTENT_META_KEY,
                $orderIntent
            );
        $wcOrder
            ->expects('update_status')
            ->with('on-hold', 'Awaiting payment.');
        $this->assertTrue($testee->process($wcOrder, $woocommerce));
    }

    public function testCapture() {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus
            ->expects('is')
            ->with(OrderStatus::APPROVED)
            ->andReturn(true);
        $orderStatus
            ->expects('is')
            ->with(OrderStatus::COMPLETED)
            ->andReturn(true);
        $orderId = 'abc';
        $orderIntent = 'CAPTURE';
        $currentOrder = Mockery::mock(Order::class);
        $currentOrder
            ->expects('id')
            ->andReturn($orderId);
        $currentOrder
            ->shouldReceive('intent')
            ->andReturn($orderIntent);
        $currentOrder
            ->shouldReceive('status')
            ->andReturn($orderStatus);
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
            ->expects('order')
            ->andReturn($currentOrder);
        $sessionHandler
            ->expects('destroySessionData');
        $cartRepository = Mockery::mock(CartRepository::class);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('patchOrderWith')
            ->with($currentOrder, $currentOrder)
            ->andReturn($currentOrder);
        $orderEndpoint
            ->expects('capture')
            ->with($currentOrder)
            ->andReturn($currentOrder);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $orderFactory
            ->expects('fromWcOrder')
            ->with($wcOrder, $currentOrder)
            ->andReturn($currentOrder);
        $threeDSecure = Mockery::mock(ThreeDSecure::class);
        $authorizedPaymentProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $settings = Mockery::mock(Settings::class);
        $settings
            ->shouldReceive('has')
            ->andReturnFalse();

        $testee = new OrderProcessor(
            $sessionHandler,
            $cartRepository,
            $orderEndpoint,
            $paymentsEndpoint,
            $orderFactory,
            $threeDSecure,
            $authorizedPaymentProcessor,
            $settings
        );

        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('empty_cart');
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $woocommerce->cart = $cart;

        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::ORDER_ID_META_KEY,
                $orderId
            );
        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::INTENT_META_KEY,
                $orderIntent
            );
        $wcOrder
            ->expects('update_status')
            ->with('on-hold', 'Awaiting payment.');
        $wcOrder
            ->expects('update_status')
            ->with('processing', 'Payment received.');
        $this->assertTrue($testee->process($wcOrder, $woocommerce));
    }

    public function testError() {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus
            ->expects('is')
            ->with(OrderStatus::APPROVED)
            ->andReturn(false);
        $orderId = 'abc';
        $orderIntent = 'CAPTURE';
        $currentOrder = Mockery::mock(Order::class);
        $currentOrder
            ->expects('id')
            ->andReturn($orderId);
        $currentOrder
            ->shouldReceive('intent')
            ->andReturn($orderIntent);
        $currentOrder
            ->shouldReceive('status')
            ->andReturn($orderStatus);
        $currentOrder
            ->shouldReceive('paymentSource')
            ->andReturnNull();
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
            ->expects('order')
            ->andReturn($currentOrder);
        $cartRepository = Mockery::mock(CartRepository::class);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $threeDSecure = Mockery::mock(ThreeDSecure::class);
        $authorizedPaymentProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $settings = Mockery::mock(Settings::class);

        $testee = new OrderProcessor(
            $sessionHandler,
            $cartRepository,
            $orderEndpoint,
            $paymentsEndpoint,
            $orderFactory,
            $threeDSecure,
            $authorizedPaymentProcessor,
            $settings
        );

        $cart = Mockery::mock(\WC_Cart::class);
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $woocommerce->cart = $cart;

        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::ORDER_ID_META_KEY,
                $orderId
            );
        $wcOrder
            ->expects('update_meta_data')
            ->with(
                PayPalGateway::INTENT_META_KEY,
                $orderIntent
            );
        $this->assertFalse($testee->process($wcOrder, $woocommerce));
        $this->assertNotEmpty($testee->last_error());
    }


}