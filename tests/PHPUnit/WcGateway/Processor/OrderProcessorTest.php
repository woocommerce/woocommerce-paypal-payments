<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;


use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CartRepository;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\WooCommerce\Logging\WooCommerceLoggingModule;
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
            ->expects('destroy_session_data');
        $cartRepository = Mockery::mock(CartRepository::class);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('patch_order_with')
            ->with($currentOrder, $currentOrder)
            ->andReturn($currentOrder);
        $orderEndpoint
            ->expects('authorize')
            ->with($currentOrder)
            ->andReturn($currentOrder);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $orderFactory
            ->expects('from_wc_order')
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
            ->expects('destroy_session_data');
        $cartRepository = Mockery::mock(CartRepository::class);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('patch_order_with')
            ->with($currentOrder, $currentOrder)
            ->andReturn($currentOrder);
        $orderEndpoint
            ->expects('capture')
            ->with($currentOrder)
            ->andReturn($currentOrder);
        $paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $orderFactory
            ->expects('from_wc_order')
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
            ->shouldReceive('payment_source')
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