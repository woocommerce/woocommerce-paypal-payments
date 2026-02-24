<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class OrderEndpointCachedTest extends TestCase
{
	private function createBearer(): Bearer
	{
		$token = Mockery::mock(Token::class);
		$bearer = Mockery::mock(Bearer::class);

		$token->shouldReceive('token')->andReturn('bearer');
		$bearer->shouldReceive('bearer')->andReturn($token);

		return $bearer;
	}

	private function setupHttpResponse(int $callCount = 1): void
	{
		expect('wp_json_encode')->andReturnUsing('json_encode');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
		$rawResponse = [
			'body' => '{}',
			'headers' => $headers,
		];

		$expectation = expect('wp_remote_get');
		$expectation->times($callCount);
		$expectation->andReturn($rawResponse);

		expect('is_wp_error')->with($rawResponse)->andReturn(false);
		expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);
	}

	private function createTestee(OrderFactory $orderFactory): OrderEndpointCached
	{
		$logger = Mockery::mock(LoggerInterface::class);
		$logger->shouldReceive('debug');

		return new OrderEndpointCached(
			'https://example.com/',
			$this->createBearer(),
			$orderFactory,
			Mockery::mock(PatchCollectionFactory::class),
			'CAPTURE',
			$logger,
			Mockery::mock(SubscriptionHelper::class),
			false,
			Mockery::mock(FraudNet::class)
		);
	}

	public function testOrderReturnsCachedResultOnSecondCall()
	{
		$orderId = 'abc123';
		$order = Mockery::mock(Order::class);
		$orderFactory = Mockery::mock(OrderFactory::class);
		$orderFactory
			->expects('from_paypal_response')
			->once()
			->andReturn($order);

		$testee = $this->createTestee($orderFactory);
		$this->setupHttpResponse(1);

		// First call should hit the API
		$result1 = $testee->order($orderId);
		$this->assertEquals($order, $result1);

		// Second call should return cached result
		$result2 = $testee->order($orderId);
		$this->assertEquals($order, $result2);
		$this->assertSame($result1, $result2);
	}

	public function testOrderWithWcOrderReturnsCachedResult()
	{
		$orderId = 'abc123';
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder
			->shouldReceive('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn($orderId);

		$order = Mockery::mock(Order::class);
		$orderFactory = Mockery::mock(OrderFactory::class);
		$orderFactory
			->expects('from_paypal_response')
			->once()
			->andReturn($order);

		$testee = $this->createTestee($orderFactory);
		$this->setupHttpResponse(1);

		// First call with WC_Order should hit the API
		$result1 = $testee->order($wcOrder);
		$this->assertEquals($order, $result1);

		// Second call with same WC_Order should return cached result
		$result2 = $testee->order($wcOrder);
		$this->assertEquals($order, $result2);
		$this->assertSame($result1, $result2);
	}

	public function testOrderWithStringAndWcOrderReturnsSameCachedResult()
	{
		$orderId = 'abc123';
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder
			->shouldReceive('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn($orderId);

		$order = Mockery::mock(Order::class);
		$orderFactory = Mockery::mock(OrderFactory::class);
		$orderFactory
			->expects('from_paypal_response')
			->once()
			->andReturn($order);

		$testee = $this->createTestee($orderFactory);
		$this->setupHttpResponse(1);

		// First call with string ID should hit the API
		$result1 = $testee->order($orderId);
		$this->assertEquals($order, $result1);

		// Second call with WC_Order (same PayPal ID) should return cached result
		$result2 = $testee->order($wcOrder);
		$this->assertEquals($order, $result2);
		$this->assertSame($result1, $result2);
	}

	public function testOrderWithDifferentIdsDoesNotReturnCachedResult()
	{
		$orderId1 = 'abc123';
		$orderId2 = 'def456';
		$order1 = Mockery::mock(Order::class);
		$order2 = Mockery::mock(Order::class);
		$orderFactory = Mockery::mock(OrderFactory::class);
		$orderFactory
			->expects('from_paypal_response')
			->twice()
			->andReturnUsing(function () use ($order1, $order2) {
				static $callCount = 0;
				$callCount++;
				return $callCount === 1 ? $order1 : $order2;
			});

		$testee = $this->createTestee($orderFactory);
		$this->setupHttpResponse(2);

		// First call with first ID
		$result1 = $testee->order($orderId1);
		$this->assertEquals($order1, $result1);

		// Second call with different ID should hit the API again
		$result2 = $testee->order($orderId2);
		$this->assertEquals($order2, $result2);
		$this->assertNotSame($result1, $result2);
	}

	public function testOrderCachesMultipleOrders()
	{
		$orderId1 = 'abc123';
		$orderId2 = 'def456';
		$order1 = Mockery::mock(Order::class);
		$order2 = Mockery::mock(Order::class);
		$orderFactory = Mockery::mock(OrderFactory::class);
		$orderFactory
			->expects('from_paypal_response')
			->twice()
			->andReturnUsing(function () use ($order1, $order2) {
				static $callCount = 0;
				$callCount++;
				return $callCount === 1 ? $order1 : $order2;
			});

		$testee = $this->createTestee($orderFactory);
		$this->setupHttpResponse(2);

		// Cache first order
		$result1 = $testee->order($orderId1);
		$this->assertEquals($order1, $result1);

		// Cache second order
		$result2 = $testee->order($orderId2);
		$this->assertEquals($order2, $result2);

		// Verify first order is still cached
		$result1Again = $testee->order($orderId1);
		$this->assertEquals($order1, $result1Again);
		$this->assertSame($result1, $result1Again);

		// Verify second order is still cached
		$result2Again = $testee->order($orderId2);
		$this->assertEquals($order2, $result2Again);
		$this->assertSame($result2, $result2Again);
	}
}
