<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;

class RenewalHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $logger;
    private $repository;
    private $orderEndpoint;
    private $purchaseUnitFactory;
    private $payerFactory;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->repository = Mockery::mock(PaymentTokenRepository::class);
        $this->orderEndpoint = Mockery::mock(OrderEndpoint::class);
        $this->purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
        $this->payerFactory = Mockery::mock(PayerFactory::class);

        $this->sut = new RenewalHandler(
            $this->logger,
            $this->repository,
            $this->orderEndpoint,
            $this->purchaseUnitFactory,
            $this->payerFactory
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenewProcessOrder()
    {
        $wcOrder = Mockery::mock(\WC_Order::class);
        $customer = Mockery::mock('overload:WC_Customer');
        $token = Mockery::mock(PaymentToken::class);
        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
        $payer = Mockery::mock(Payer::class);
        $order = Mockery::mock(Order::class);

        $this->logger->shouldReceive('log');
        $wcOrder
            ->shouldReceive('get_id')
            ->andReturn(1);
        $wcOrder
            ->shouldReceive('get_customer_id')
            ->andReturn(1);
        $this->repository->shouldReceive('for_user_id')
            ->andReturn($token);
        $customer->shouldReceive('get_id')
            ->andReturn(1);
        $this->purchaseUnitFactory->shouldReceive('from_wc_order')
            ->andReturn($purchaseUnit);
        $this->payerFactory->shouldReceive('from_customer')
            ->andReturn($payer);

        $this->orderEndpoint->shouldReceive('create')
            ->with([$purchaseUnit], $payer, $token)
            ->andReturn($order);

        $order->shouldReceive('intent')
            ->andReturn('CAPTURE');
        $order->shouldReceive('status->is')
            ->andReturn(true);
        $wcOrder->shouldReceive('update_status');

        $this->sut->renew($wcOrder);
    }
}
