<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSource;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class PayUponInvoiceOrderEndpointTest extends TestCase
{
	private $bearer;
	private $orderFactory;
	private $fraudnet;
	private $logger;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->bearer = Mockery::mock(Bearer::class);
		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('');
		$this->bearer->shouldReceive('bearer')->andReturn($token);

		$this->orderFactory = Mockery::mock(OrderFactory::class);
		$this->fraudnet = Mockery::mock(FraudNet::class);
		$this->logger = Mockery::mock(LoggerInterface::class);

		$this->testee = new PayUponInvoiceOrderEndpoint(
			'',
			$this->bearer,
			$this->orderFactory,
			$this->fraudnet,
			$this->logger
		);
	}

	public function testCreateOrder()
	{
		list($items, $paymentSource, $headers) = $this->setStubs();

		$response = [
			'body' => '{"is_correct":true}',
			'headers' => $headers,
		];
		expect('wp_remote_get')->andReturn($response);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(200);

		$this->logger->shouldReceive('debug');

		$result = $this->testee->create($items, $paymentSource, '');
		$this->assertInstanceOf(Order::class, $result);
	}

	public function testCreateOrderWpError()
	{
		list($items, $paymentSource) = $this->setStubsForError();

		$wpError = Mockery::mock(\WP_Error::class);
		$wpError->shouldReceive('get_error_messages')->andReturn(['foo']);
		$wpError->shouldReceive('get_error_message')->andReturn('foo');
		expect('wp_remote_get')->andReturn($wpError);

		$this->logger->shouldReceive('debug');

		$this->expectException(\RuntimeException::class);
		$this->testee->create($items, $paymentSource, '');
	}

	public function testCreateOrderApiError()
	{
		list($items, $paymentSource) = $this->setStubsForError();

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
		$response = [
			'body' => '{"is_correct":true}',
			'headers' => $headers,
		];

		when('get_bloginfo')->justReturn('de-DE');
		expect('wp_remote_get')->andReturn($response);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(500);

		$this->logger->shouldReceive('debug');

		$this->expectException(PayPalApiException::class);
		$this->testee->create($items, $paymentSource, '');
	}

	/**
	 * @return array
	 */
	private function setStubs(): array
	{
		$order = Mockery::mock(Order::class);
		$this->orderFactory
			->expects('from_paypal_response')
			->andReturnUsing(function (\stdClass $object) use ($order): ?Order {
				return ($object->is_correct) ? $order : null;
			});

		$this->fraudnet->shouldReceive('session_id')->andReturn('');

		$purchaseUnit = Mockery::mock(PurchaseUnit::class);
		$purchaseUnit->shouldReceive('to_array')->andReturn([]);
		$items = [$purchaseUnit];

		$paymentSource = Mockery::mock(PaymentSource::class);
		$paymentSource->shouldReceive('to_array')->andReturn([]);

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
		return array($items, $paymentSource, $headers);
	}

	/**
	 * @return array
	 */
	private function setStubsForError(): array
	{
		$this->fraudnet->shouldReceive('session_id')->andReturn('');
		$purchaseUnit = Mockery::mock(PurchaseUnit::class);
		$purchaseUnit->shouldReceive('to_array')->andReturn([]);
		$items = [$purchaseUnit];
		$paymentSource = Mockery::mock(PaymentSource::class);
		$paymentSource->shouldReceive('to_array')->andReturn([]);
		return array($items, $paymentSource);
	}
}
