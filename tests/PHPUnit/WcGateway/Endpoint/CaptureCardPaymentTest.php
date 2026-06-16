<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CaptureCardPaymentTest extends TestCase
{
	/**
	 * Regression test for #4353: when PayPal's v2/checkout/orders response is an
	 * error (non-2xx, no `id`), create_order() must surface a PayPalApiException
	 * with PayPal's actual error, not the opaque "Order does not contain an id."
	 * RuntimeException raised by OrderFactory when it parses an error body.
	 */
	public function testCreateOrderThrowsPayPalApiExceptionOnErrorResponse(): void
	{
		when('wp_json_encode')->alias('json_encode');
		when('trailingslashit')->returnArg();
		when('wp_remote_retrieve_response_code')->justReturn(422);

		$host = 'https://example.com/';

		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('bearer');
		$bearer = Mockery::mock(Bearer::class);
		$bearer->shouldReceive('bearer')->andReturn($token);

		$purchaseUnit = Mockery::mock(PurchaseUnit::class);
		$purchaseUnit->shouldReceive('to_array')->andReturn([]);
		$purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
		$purchaseUnitFactory->shouldReceive('from_wc_order')->andReturn($purchaseUnit);

		// If the response validation is missing, the error body falls through to
		// the factory; we let it "succeed" here so the only way the test passes
		// is the new PayPalApiException guard firing first.
		$orderFactory = Mockery::mock(OrderFactory::class);
		$orderFactory->shouldReceive('from_paypal_response')->andReturn(Mockery::mock(Order::class));

		$settingsProvider = Mockery::mock(SettingsProvider::class);
		$settingsProvider->shouldReceive('payment_intent')->andReturn('CAPTURE');

		$logger = Mockery::mock(LoggerInterface::class);
		$logger->shouldReceive('debug');
		$logger->shouldReceive('warning');

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll')->andReturn([]);

		$rawResponse = [
			'body'    => '{"name":"UNPROCESSABLE_ENTITY","message":"The requested action could not be performed."}',
			'headers' => $headers,
		];

		expect('wp_remote_get')->andReturn($rawResponse);

		$testee = new CaptureCardPayment(
			$host,
			$bearer,
			$orderFactory,
			$purchaseUnitFactory,
			$settingsProvider,
			$logger
		);

		$this->expectException(PayPalApiException::class);

		$testee->create_order('vault-123', Mockery::mock(WC_Order::class));
	}
}
