<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class PaymentTokensEndpointTest extends TestCase
{
	/**
	 * @var PaymentTokensEndpoint
	 */
	private $endpoint;

	/**
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var string
	 */
	private $host = 'https://api.sandbox.paypal.com';

	public function setUp(): void
	{
		parent::setUp();

		$this->bearer = Mockery::mock(Bearer::class);
		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('test_bearer_token_12345');
		$this->bearer->shouldReceive('bearer')->andReturn($token);

		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->logger->shouldReceive('debug')->andReturnNull();

		when('trailingslashit')->returnArg();
		when('wp_remote_retrieve_response_code')->alias(function($response) {
			return $response['response']['code'] ?? 0;
		});
		when('json_decode')->alias(function($json) {
			return \json_decode($json);
		});
		when('wp_json_encode')->alias(function($data) {
			return \json_encode($data);
		});
		when('wc_print_r')->alias(function($data) {
			return print_r($data, true);
		});

		$this->endpoint = new PaymentTokensEndpoint(
			$this->host,
			$this->bearer,
			$this->logger
		);
	}

	/**
	 * Test that delete() successfully deletes a payment token.
	 */
	public function testDeleteSuccessfullyDeletesPaymentToken(): void
	{
		$token_id = 'tok_test_12345';

		$response = [
			'response' => [
				'code' => 204,
				'message' => 'No Content',
			],
			'body' => '',
			'headers' => $this->createMockHeaders(),
		];

		when('wp_remote_get')->justReturn($response);

		$this->endpoint->delete($token_id);

		$this->assertTrue(true);
	}

	/**
	 * Test that delete() throws RuntimeException when HTTP request fails.
	 */
	public function testDeleteThrowsRuntimeExceptionOnRequestFailure(): void
	{
		$token_id = 'tok_test_12345';

		$error = Mockery::mock('WP_Error');
		$error->shouldReceive('get_error_message')->andReturn('Connection timeout');
		$error->shouldReceive('get_error_messages')->andReturn(['Connection timeout']);
		when('wp_remote_get')->justReturn($error);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Connection timeout');

		$this->endpoint->delete($token_id);
	}

	/**
	 * Test that delete() throws PayPalApiException when API returns error.
	 */
	public function testDeleteThrowsPayPalApiExceptionOnApiError(): void
	{
		$token_id = 'tok_test_12345';

		$error_body = json_encode([
			'name' => 'RESOURCE_NOT_FOUND',
			'message' => 'The specified resource does not exist.',
			'debug_id' => 'test_debug_id',
		]);

		$response = [
			'response' => [
				'code' => 404,
				'message' => 'Not Found',
			],
			'body' => $error_body,
			'headers' => $this->createMockHeaders(),
		];

		when('wp_remote_get')->justReturn($response);

		$this->expectException(PayPalApiException::class);

		$this->endpoint->delete($token_id);
	}

	/**
	 * Test that payment_tokens_for_customer() returns payment tokens successfully.
	 */
	public function testPaymentTokensForCustomerReturnsTokens(): void
	{
		$customer_id = 'cust_12345';

		$response_body = json_encode([
			'payment_tokens' => [
				[
					'id' => 'tok_card',
					'payment_source' => [
						'card' => [
							'brand' => 'VISA',
							'last_digits' => '1234',
							'expiry' => '2025-12',
						],
					],
				],
				[
					'id' => 'tok_paypal',
					'payment_source' => [
						'paypal' => [
							'email_address' => 'test@example.com',
							'payer_id' => 'PAYER123',
						],
					],
				],
			],
		]);

		$response = [
			'response' => [
				'code' => 200,
				'message' => 'OK',
			],
			'body' => $response_body,
			'headers' => $this->createMockHeaders(),
		];

		when('wp_remote_get')->justReturn($response);

		$tokens = $this->endpoint->payment_tokens_for_customer($customer_id);

		$this->assertCount(2, $tokens);

		$this->assertArrayHasKey('id', $tokens[0]);
		$this->assertArrayHasKey('payment_source', $tokens[0]);
		$this->assertEquals('tok_card', $tokens[0]['id']);
		$this->assertEquals('card', $tokens[0]['payment_source']->name());

		$this->assertEquals('tok_paypal', $tokens[1]['id']);
		$this->assertEquals('paypal', $tokens[1]['payment_source']->name());
	}

	/**
	 * Test that payment_tokens_for_customer() returns empty array when no tokens exist.
	 */
	public function testPaymentTokensForCustomerReturnsEmptyArrayWhenNoTokens(): void
	{
		$customer_id = 'cust_12345';

		$response_body = json_encode([
			'payment_tokens' => [],
		]);

		$response = [
			'response' => [
				'code' => 200,
				'message' => 'OK',
			],
			'body' => $response_body,
			'headers' => $this->createMockHeaders(),
		];

		when('wp_remote_get')->justReturn($response);

		$tokens = $this->endpoint->payment_tokens_for_customer($customer_id);

		$this->assertIsArray($tokens);
		$this->assertEmpty($tokens);
	}

	/**
	 * Test that payment_tokens_for_customer() throws RuntimeException on request failure.
	 */
	public function testPaymentTokensForCustomerThrowsRuntimeExceptionOnRequestFailure(): void
	{
		$customer_id = 'cust_12345';

		$error = Mockery::mock('WP_Error');
		$error->shouldReceive('get_error_message')->andReturn('Network error');
		$error->shouldReceive('get_error_messages')->andReturn(['Network error']);
		when('wp_remote_get')->justReturn($error);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Network error');

		$this->endpoint->payment_tokens_for_customer($customer_id);
	}

	/**
	 * Test that payment_tokens_for_customer() throws PayPalApiException on API error.
	 */
	public function testPaymentTokensForCustomerThrowsPayPalApiExceptionOnApiError(): void
	{
		$customer_id = 'cust_12345';

		$error_body = json_encode([
			'name' => 'PERMISSION_DENIED',
			'message' => 'You do not have permission to access this resource',
		]);

		$response = [
			'response' => [
				'code' => 403,
				'message' => 'Forbidden',
			],
			'body' => $error_body,
			'headers' => $this->createMockHeaders(),
		];

		when('wp_remote_get')->justReturn($response);

		$this->expectException(PayPalApiException::class);

		$this->endpoint->payment_tokens_for_customer($customer_id);
	}

	/**
	 * Test that payment_tokens_for_customer() skips tokens without valid payment source.
	 */
	public function testPaymentTokensForCustomerSkipsTokensWithoutValidPaymentSource(): void
	{
		$customer_id = 'cust_12345';

		$response_body = json_encode([
			'payment_tokens' => [
				[
					'id' => 'tok_valid',
					'payment_source' => [
						'card' => [
							'brand' => 'VISA',
							'last_digits' => '1234',
						],
					],
				],
				[
					'id' => 'tok_invalid',
					'payment_source' => [], // Empty payment source
				],
				[
					'id' => 'tok_valid_2',
					'payment_source' => [
						'paypal' => [
							'email_address' => 'test@example.com',
						],
					],
				],
			],
		]);

		$response = [
			'response' => [
				'code' => 200,
				'message' => 'OK',
			],
			'body' => $response_body,
			'headers' => $this->createMockHeaders(),
		];

		when('wp_remote_get')->justReturn($response);

		$tokens = $this->endpoint->payment_tokens_for_customer($customer_id);

		$this->assertCount(2, $tokens);
		$this->assertEquals('tok_valid', $tokens[0]['id']);
		$this->assertEquals('tok_valid_2', $tokens[1]['id']);
	}
	/**
	 * Creates a mock headers object.
	 *
	 * @param array $headers The headers array.
	 * @return object
	 */
	private function createMockHeaders(array $headers = []): object
	{
		return new class($headers) {
			private $headers;

			public function __construct(array $headers)
			{
				$this->headers = $headers;
			}

			public function getAll(): array
			{
				return $this->headers;
			}
		};
	}

}
