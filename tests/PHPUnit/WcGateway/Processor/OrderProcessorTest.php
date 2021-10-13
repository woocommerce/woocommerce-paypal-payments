<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;


use Dhii\Container\Dictionary;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use Mockery;
use function Brain\Monkey\Functions\when;

class OrderProcessorTest extends TestCase
{
	private $environment;

	public function setUp(): void {
		parent::setUp();

		$this->environment = new Environment(new Dictionary([]));
	}

    public function testAuthorize() {
        $transactionId = 'ABC123';

        $authorization = Mockery::mock(Authorization::class);
        $authorization->shouldReceive('id')
            ->andReturn($transactionId);
		$authorization->shouldReceive('status')
			->andReturn(new AuthorizationStatus(AuthorizationStatus::CREATED));

        $payments = Mockery::mock(Payments::class);
        $payments->shouldReceive('authorizations')
            ->andReturn([$authorization]);
        $payments->shouldReceive('captures')
            ->andReturn([]);

        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnit->shouldReceive('payments')
            ->andReturn($payments);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('update_meta_data')
            ->with(PayPalGateway::ORDER_PAYMENT_MODE_META_KEY, 'live');

        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus
            ->shouldReceive('is')
            ->with(OrderStatus::APPROVED)
            ->andReturn(true);
        $orderStatus
            ->shouldReceive('is')
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
        $currentOrder->shouldReceive('purchase_units')
            ->andReturn([$purchaseUnit]);

        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
            ->expects('order')
            ->andReturn($currentOrder);
        $sessionHandler
            ->expects('destroy_session_data');

        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('patch_order_with')
            ->with($currentOrder, $currentOrder)
            ->andReturn($currentOrder);
        $orderEndpoint
            ->expects('authorize')
            ->with($currentOrder)
            ->andReturn($currentOrder);

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

        $logger = Mockery::mock(LoggerInterface::class);

        $testee = new OrderProcessor(
            $sessionHandler,
            $orderEndpoint,
            $orderFactory,
            $threeDSecure,
            $authorizedPaymentProcessor,
            $settings,
            $logger,
            $this->environment
        );

        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('empty_cart');
        $woocommerce = Mockery::mock(\WooCommerce::class);
        when('WC')
			->justReturn($woocommerce);

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


        $this->assertTrue($testee->process($wcOrder));
    }

    public function testCapture() {
        $transactionId = 'ABC123';

        $capture = Mockery::mock(Capture::class);
        $capture->expects('id')
            ->andReturn($transactionId);
        $capture->expects('status')
            ->andReturn(new CaptureStatus(CaptureStatus::COMPLETED));

        $payments = Mockery::mock(Payments::class);
        $payments->shouldReceive('captures')
            ->andReturn([$capture]);

        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnit->shouldReceive('payments')
            ->andReturn($payments);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus
            ->shouldReceive('is')
            ->with(OrderStatus::APPROVED)
            ->andReturn(true);
        $orderStatus
            ->shouldReceive('is')
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
        $currentOrder
            ->shouldReceive('purchase_units')
            ->andReturn([$purchaseUnit]);
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
            ->expects('order')
            ->andReturn($currentOrder);
        $sessionHandler
            ->expects('destroy_session_data');
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderEndpoint
            ->expects('patch_order_with')
            ->with($currentOrder, $currentOrder)
            ->andReturn($currentOrder);
        $orderEndpoint
            ->expects('capture')
            ->with($currentOrder)
            ->andReturn($currentOrder);
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

        $cart = Mockery::mock(\WC_Cart::class);
        $cart
			->shouldReceive('empty_cart');

        $woocommerce = Mockery::Mock(\Woocommerce::class);
		$woocommerce
			->shouldReceive('__get')
			->with('cart')
			->set('cart', $cart);
        when('WC')
			->justReturn($woocommerce);

		$logger = Mockery::mock(LoggerInterface::class);


		$testee = new OrderProcessor(
            $sessionHandler,
            $orderEndpoint,
            $orderFactory,
            $threeDSecure,
            $authorizedPaymentProcessor,
            $settings,
            $logger,
            $this->environment
        );

        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('empty_cart');
        $woocommerce = Mockery::mock(\WooCommerce::class);
        $woocommerce->cart = $cart;

        when('WC')
			->justReturn($woocommerce);

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
        $wcOrder->expects('update_meta_data')
            ->with(PayPalGateway::ORDER_PAYMENT_MODE_META_KEY, 'live');
        $wcOrder->expects('set_transaction_id')
            ->with($transactionId);
        $wcOrder
	        ->expects('payment_complete');
        $this->assertTrue($testee->process($wcOrder));
    }

    public function testError() {
        $transactionId = 'ABC123';

        $capture = Mockery::mock(Capture::class);
        $capture->shouldReceive('id')
            ->andReturn($transactionId);

        $payments = Mockery::mock(Payments::class);
        $payments->shouldReceive('captures')
            ->andReturn([$capture]);

        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $purchaseUnit->shouldReceive('payments')
            ->andReturn($payments);

        $wcOrder = Mockery::mock(\WC_Order::class);
        $wcOrder->expects('update_meta_data')
            ->with(PayPalGateway::ORDER_PAYMENT_MODE_META_KEY, 'live');
        $wcOrder->shouldReceive('set_transaction_id')
            ->with($transactionId);

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
        $currentOrder
            ->shouldReceive('purchase_units')
            ->andReturn([$purchaseUnit]);
        $sessionHandler = Mockery::mock(SessionHandler::class);
        $sessionHandler
            ->expects('order')
            ->andReturn($currentOrder);
        $orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $threeDSecure = Mockery::mock(ThreeDSecure::class);
        $authorizedPaymentProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
        $settings = Mockery::mock(Settings::class);

		$logger = Mockery::mock(LoggerInterface::class);

		$testee = new OrderProcessor(
            $sessionHandler,
            $orderEndpoint,
            $orderFactory,
            $threeDSecure,
            $authorizedPaymentProcessor,
            $settings,
            $logger,
            $this->environment
        );

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

        $this->assertFalse($testee->process($wcOrder));
        $this->assertNotEmpty($testee->last_error());
    }


}
