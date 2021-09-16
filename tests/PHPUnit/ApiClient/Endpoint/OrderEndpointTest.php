<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Hamcrest\Matchers;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use Woocommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ErrorResponse;
use WooCommerce\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;
use Psr\Log\LoggerInterface;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class OrderEndpointTest extends TestCase
{

	public function setUp(): void
	{
		parent::setUp();
		when('wc_print_r')->returnArg();
	}

	public function testOrderDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $order = Mockery::mock(Order::class);
        $orderFactory
            ->expects('from_paypal_response')
            ->andReturnUsing(function (\stdClass $object) use ($order) : ?Order {
                return ($object->is_correct) ? $order : null;
            });
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order_id')->with($orderId)->andReturn('uniqueRequestId');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
		];
        expect('wp_remote_get')
            ->andReturnUsing(function ($url, $args) use ($rawResponse, $host, $orderId) {
                if ($url !== $host . 'v2/checkout/orders/' . $orderId) {
                    return false;
                }
                if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                    return false;
                }
                if ($args['headers']['Content-Type'] !== 'application/json') {
                    return false;
                }
                return $rawResponse;
            });
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);

        $result = $testee->order($orderId);
        $this->assertEquals($order, $result);
    }

    public function testOrderResponseIsWpError()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $orderId = 'id';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order_id')->with($orderId)->andReturn('uniqueRequestId');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
		];
        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);

        $this->expectException(RuntimeException::class);
        $testee->order($orderId);
    }

    public function testOrderResponseIsNot200()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $orderId = 'id';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"some_error":true}',
			'headers' => $headers,
		];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order_id')->with($orderId)->andReturn('uniqueRequestId');

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);

        $this->expectException(RuntimeException::class);
        $testee->order($orderId);
    }

    public function testCaptureDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToCaptureStatus = Mockery::mock(OrderStatus::class);
        $orderToCaptureStatus->expects('is')->with('COMPLETED')->andReturn(false);
        $orderToCapture = Mockery::mock(Order::class);
        $orderToCapture->expects('status')->andReturn($orderToCaptureStatus);
        $orderToCapture->expects('id')->andReturn($orderId);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
		];
        $expectedOrder = Mockery::mock(Order::class);
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $orderFactory
            ->expects('from_paypal_response')
            ->andReturnUsing(
                function ($json) use ($expectedOrder) {
                    if ($json->is_correct) {
                        return $expectedOrder;
                    }
                    return Mockery::mock(Order::class);
                }
            );
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToCapture)->andReturn('uniqueRequestId');
        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($rawResponse, $host, $orderId) {
                    if ($url !== $host . 'v2/checkout/orders/' . $orderId . '/capture') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(201);
        $purchaseUnit = Mockery::mock(PurchaseUnit::class);
	    $payment = Mockery::mock(Payments::class);
	    $capture = Mockery::mock(Capture::class);
	    $expectedOrder->shouldReceive('purchase_units')->once()->andReturn(['0'=>$purchaseUnit]);
	    $purchaseUnit->shouldReceive('payments')->once()->andReturn($payment);
	    $payment->shouldReceive('captures')->once()->andReturn(['0'=>$capture]);
	    $capture->shouldReceive('status')->once()->andReturn('');

        $result = $testee->capture($orderToCapture);
        $this->assertEquals($expectedOrder, $result);
    }

    public function testCaptureAlreadyCompletedOrder()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderToCaptureStatus = Mockery::mock(OrderStatus::class);
        $orderToCaptureStatus->expects('is')->with('COMPLETED')->andReturn(true);
        $orderToCapture = Mockery::mock(Order::class);
        $orderToCapture->expects('status')->andReturn($orderToCaptureStatus);

        $host = 'https://example.com/';
        $bearer = Mockery::mock(Bearer::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $result = $testee->capture($orderToCapture);
        $this->assertEquals($orderToCapture, $result);
    }

    public function testCaptureIsWpError()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToCaptureStatus = Mockery::mock(OrderStatus::class);
        $orderToCaptureStatus->expects('is')->with('COMPLETED')->andReturn(false);
        $orderToCapture = Mockery::mock(Order::class);
        $orderToCapture->expects('status')->andReturn($orderToCaptureStatus);
        $orderToCapture->expects('id')->andReturn($orderId);

        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToCapture)->andReturn('uniqueRequestId');
        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_error":true}',
			'headers' => $headers,
		];
        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        $this->expectException(RuntimeException::class);
        $testee->capture($orderToCapture);
    }

    public function testCaptureIsNot201()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToCaptureStatus = Mockery::mock(OrderStatus::class);
        $orderToCaptureStatus->expects('is')->with('COMPLETED')->andReturn(false);
        $orderToCapture = Mockery::mock(Order::class);
        $orderToCapture->expects('status')->andReturn($orderToCaptureStatus);
        $orderToCapture->expects('id')->andReturn($orderId);

        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToCapture)->andReturn('uniqueRequestId');
        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"some_error":true}',
			'headers' => $headers
		];
        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        $this->expectException(RuntimeException::class);
        $testee->capture($orderToCapture);
    }

    public function testCaptureIsNot201ButAlreadyCaptured()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToCaptureStatus = Mockery::mock(OrderStatus::class);
        $orderToCaptureStatus->expects('is')->with('COMPLETED')->andReturn(false);
        $orderToCapture = Mockery::mock(Order::class);
        $orderToCapture->expects('status')->andReturn($orderToCaptureStatus);
        $orderToCapture->shouldReceive('id')->andReturn($orderId);

        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToCapture)->andReturn('uniqueRequestId');
        $testee = Mockery::mock(
            OrderEndpoint::class,
            [
                $host,
                $bearer,
                $orderFactory,
                $patchCollectionFactory,
                $intent,
                $logger,
                $applicationContextRepository,
                $paypalRequestIdRepository,
            ]
        )->makePartial();
        $orderToExpect = Mockery::mock(Order::class);
        $testee->expects('order')->with($orderId)->andReturn($orderToExpect);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"some_error": "' . ErrorResponse::ORDER_ALREADY_CAPTURED . '"}',
			'headers' => $headers,
		];
        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        $result = $testee->capture($orderToCapture);
        $this->assertEquals($orderToExpect, $result);
    }

    public function testPatchOrderWithDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToUpdate = Mockery::mock(Order::class);
        $orderToUpdate
            ->shouldReceive('id')
            ->andReturn($orderId);
        $orderToUpdate
            ->shouldReceive('purchase_units')
            ->andReturn([]);
        $orderToCompare = Mockery::mock(Order::class);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
		];
        $expectedOrder = Mockery::mock(Order::class);
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patches = ['patch-1', 'patch-2'];
        $patchCollection = Mockery::mock(PatchCollection::class);
        $patchCollection
            ->expects('patches')
            ->andReturn($patches);
        $patchCollection
            ->expects('to_array')
            ->andReturn($patches);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $patchCollectionFactory
            ->expects('from_orders')
            ->with($orderToUpdate, $orderToCompare)
            ->andReturn($patchCollection);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToUpdate)->andReturn('uniqueRequestId');
        $testee = Mockery::mock(
            OrderEndpoint::class,
            [
                $host,
                $bearer,
                $orderFactory,
                $patchCollectionFactory,
                $intent,
                $logger,
                $applicationContextRepository,
                $paypalRequestIdRepository,
            ]
        )->makePartial();
        $testee
            ->expects('order')
            ->with($orderId)
            ->andReturn($expectedOrder);

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($host, $orderId, $rawResponse) {
                    if ($url !== $host . 'v2/checkout/orders/' . $orderId) {
                        return false;
                    }
                    if ($args['method'] !== 'PATCH') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    if ($args['headers']['PayPal-Request-Id'] !== 'uniqueRequestId') {
                        return false;
                    }
                    $body = json_decode($args['body']);
                    if (! is_array($body) || $body[0] !== 'patch-1' || $body[1] !== 'patch-2') {
                        return false;
                    }

                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(204);
        $result = $testee->patch_order_with($orderToUpdate, $orderToCompare);
        $this->assertEquals($expectedOrder, $result);
    }

    public function testPatchOrderWithIsNot204()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToUpdate = Mockery::mock(Order::class);
        $orderToUpdate
            ->shouldReceive('id')
            ->andReturn($orderId);
        $orderToUpdate
            ->shouldReceive('purchase_units')
            ->andReturn([]);
        $orderToCompare = Mockery::mock(Order::class);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"has_error":true}',
			'headers' => $headers,
			];
        $expectedOrder = Mockery::mock(Order::class);
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patches = ['patch-1', 'patch-2'];
        $patchCollection = Mockery::mock(PatchCollection::class);
        $patchCollection
            ->expects('patches')
            ->andReturn($patches);
        $patchCollection
            ->expects('to_array')
            ->andReturn($patches);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $patchCollectionFactory
            ->expects('from_orders')
            ->with($orderToUpdate, $orderToCompare)
            ->andReturn($patchCollection);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToUpdate)->andReturn('uniqueRequestId');

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($host, $orderId, $rawResponse) {
                    if ($url !== $host . 'v2/checkout/orders/' . $orderId) {
                        return false;
                    }
                    if ($args['method'] !== 'PATCH') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    if ($args['headers']['PayPal-Request-Id'] !== 'uniqueRequestId') {
                        return false;
                    }
                    $body = json_decode($args['body']);
                    if (! is_array($body) || $body[0] !== 'patch-1' || $body[1] !== 'patch-2') {
                        return false;
                    }

                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        $this->expectException(RuntimeException::class);
        $testee->patch_order_with($orderToUpdate, $orderToCompare);
    }

    public function testPatchOrderWithIsWpError()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderId = 'id';
        $orderToUpdate = Mockery::mock(Order::class);
        $orderToUpdate
            ->shouldReceive('id')
            ->andReturn($orderId);
        $orderToUpdate
            ->shouldReceive('purchase_units')
            ->andReturn([]);
        $orderToCompare = Mockery::mock(Order::class);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
		];
        $expectedOrder = Mockery::mock(Order::class);
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patches = ['patch-1', 'patch-2'];
        $patchCollection = Mockery::mock(PatchCollection::class);
        $patchCollection
            ->expects('patches')
            ->andReturn($patches);
        $patchCollection
            ->expects('to_array')
            ->andReturn($patches);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $patchCollectionFactory
            ->expects('from_orders')
            ->with($orderToUpdate, $orderToCompare)
            ->andReturn($patchCollection);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');

        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('get_for_order')->with($orderToUpdate)->andReturn('uniqueRequestId');
        $testee = Mockery::mock(
            OrderEndpoint::class,
            [
                $host,
                $bearer,
                $orderFactory,
                $patchCollectionFactory,
                $intent,
                $logger,
                $applicationContextRepository,
                $paypalRequestIdRepository
            ]
        )->makePartial();

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($host, $orderId, $rawResponse) {
                    if ($url !== $host . 'v2/checkout/orders/' . $orderId) {
                        return false;
                    }
                    if ($args['method'] !== 'PATCH') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    if ($args['headers']['PayPal-Request-Id'] !== 'uniqueRequestId') {
                        return false;
                    }
                    $body = json_decode($args['body']);
                    if (! is_array($body) || $body[0] !== 'patch-1' || $body[1] !== 'patch-2') {
                        return false;
                    }

                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        $this->expectException(RuntimeException::class);
        $testee->patch_order_with($orderToUpdate, $orderToCompare);
    }

    public function testPatchOrderWithNoPatches()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $orderToUpdate = Mockery::mock(Order::class);
        $orderToCompare = Mockery::mock(Order::class);

        $host = 'https://example.com/';
        $bearer = Mockery::mock(Bearer::class);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patches = [];
        $patchCollection = Mockery::mock(PatchCollection::class);
        $patchCollection
            ->expects('patches')
            ->andReturn($patches);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $patchCollectionFactory
            ->expects('from_orders')
            ->with($orderToUpdate, $orderToCompare)
            ->andReturn($patchCollection);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $result = $testee->patch_order_with($orderToUpdate, $orderToCompare);
        $this->assertEquals($orderToUpdate, $result);
    }

    public function testCreateForPurchaseUnitsDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"success":true}',
			'headers' => $headers,
			];
        $host = 'https://example.com/';
        $bearer = Mockery::mock(Bearer::class);
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer
            ->expects('bearer')
            ->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $expectedOrder = Mockery::mock(Order::class);
        $orderFactory
            ->expects('from_paypal_response')
            ->andReturnUsing(function ($json) use ($expectedOrder, $rawResponse) {
                if (! $json->success) {
                    return Mockery::mock(Order::class);
                }
                return $expectedOrder;
            });
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');
        $applicationContext = Mockery::mock(ApplicationContext::class);
        $applicationContext
            ->expects('to_array')
            ->andReturn(['applicationContext']);
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationContextRepository
            ->expects('current_context')
            ->with(Matchers::identicalTo(ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING))
            ->andReturn($applicationContext);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('set_for_order')->andReturnUsing(function ($order, $id) use ($expectedOrder) : bool {
                if ($order !== $expectedOrder) {
                    return false;
                }

                return strpos($id, 'ppcp') !== false;
            });

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $purchaseUnit = Mockery::mock(PurchaseUnit::class, ['contains_physical_goods' => false]);
        $purchaseUnit
            ->expects('to_array')
            ->andReturn(['singlePurchaseUnit']);

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($rawResponse, $host) {
                    if ($url !== $host . 'v2/checkout/orders') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    $body = json_decode($args['body'], true);
                    if ($body['intent'] !== 'CAPTURE') {
                        return false;
                    }
                    if ($body['purchase_units'][0][0] !== 'singlePurchaseUnit') {
                        return false;
                    }
                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(201);
        $result = $testee->create([$purchaseUnit]);
        $this->assertEquals($expectedOrder, $result);
    }

    public function testCreateForPurchaseUnitsWithPayer()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"success":true}',
			'headers' => $headers,
			];
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')
            ->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $expectedOrder = Mockery::mock(Order::class);
        $orderFactory
            ->expects('from_paypal_response')
            ->andReturnUsing(function ($json) use ($expectedOrder, $rawResponse) {
                if (! $json->success) {
                    return Mockery::mock(Order::class);
                }
                return $expectedOrder;
            });
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');
        $applicationContext = Mockery::mock(ApplicationContext::class);
        $applicationContext
            ->expects('to_array')
            ->andReturn(['applicationContext']);
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationContextRepository
            ->expects('current_context')
            ->with(Matchers::identicalTo(ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE))
            ->andReturn($applicationContext);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $paypalRequestIdRepository
            ->expects('set_for_order')->andReturnUsing(function ($order, $id) use ($expectedOrder) : bool {
                if ($order !== $expectedOrder) {
                    return false;
                }

                return strpos($id, 'ppcp') !== false;
            });

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $purchaseUnit = Mockery::mock(PurchaseUnit::class, ['contains_physical_goods' => true]);
        $purchaseUnit
            ->expects('to_array')
            ->andReturn(['singlePurchaseUnit']);

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($rawResponse, $host) {
                    $body = json_decode($args['body'], true);
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if (! isset($body['payer']) || $body['payer'][0] !== 'payer') {
                        return false;
                    }
                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(201);

        $payer = Mockery::mock(Payer::class);
        $payer->expects('to_array')->andReturn(['payer']);
        $result = $testee->create([$purchaseUnit], $payer);
        $this->assertEquals($expectedOrder, $result);
    }

    public function testCreateForPurchaseUnitsIsWpError()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"success":true}',
			'headers' => $headers,
			];
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')
            ->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContext = Mockery::mock(ApplicationContext::class);
        $applicationContext
            ->expects('to_array')
            ->andReturn(['applicationContext']);
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationContextRepository
            ->expects('current_context')
            ->with(Matchers::identicalTo(ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING))
            ->andReturn($applicationContext);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);

        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $purchaseUnit = Mockery::mock(PurchaseUnit::class, ['contains_physical_goods' => false]);
        $purchaseUnit
            ->expects('to_array')
            ->andReturn(['singlePurchaseUnit']);

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($rawResponse, $host) {
                    if ($url !== $host . 'v2/checkout/orders') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    $body = json_decode($args['body'], true);
                    if ($body['intent'] !== 'CAPTURE') {
                        return false;
                    }
                    if ($body['purchase_units'][0][0] !== 'singlePurchaseUnit') {
                        return false;
                    }
                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        $this->expectException(RuntimeException::class);
        $testee->create([$purchaseUnit]);
    }

    public function testCreateForPurchaseUnitsIsNot201()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"has_error":true}',
			'headers' => $headers,
			];
        $host = 'https://example.com/';
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')
            ->andReturn($token);
        $orderFactory = Mockery::mock(OrderFactory::class);
        $patchCollectionFactory = Mockery::mock(PatchCollectionFactory::class);
        $intent = 'CAPTURE';

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');
        $applicationContext = Mockery::mock(ApplicationContext::class);
        $applicationContext
            ->expects('to_array')
            ->andReturn(['applicationContext']);
        $applicationContextRepository = Mockery::mock(ApplicationContextRepository::class);
        $applicationContextRepository
            ->expects('current_context')
            ->with(Matchers::identicalTo(ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE))
            ->andReturn($applicationContext);
        $paypalRequestIdRepository = Mockery::mock(PayPalRequestIdRepository::class);
        $testee = new OrderEndpoint(
            $host,
            $bearer,
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestIdRepository
        );

        $purchaseUnit = Mockery::mock(PurchaseUnit::class, ['contains_physical_goods' => true]);
        $purchaseUnit
            ->expects('to_array')
            ->andReturn(['singlePurchaseUnit']);

        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($rawResponse, $host) {
                    if ($url !== $host . 'v2/checkout/orders') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return false;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return false;
                    }
                    if ($args['headers']['Prefer'] !== 'return=representation') {
                        return false;
                    }
                    $body = json_decode($args['body'], true);
                    if ($body['intent'] !== 'CAPTURE') {
                        return false;
                    }
                    if ($body['purchase_units'][0][0] !== 'singlePurchaseUnit') {
                        return false;
                    }
                    return $rawResponse;
                }
            );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        $this->expectException(RuntimeException::class);
        $testee->create([$purchaseUnit]);
    }
}
