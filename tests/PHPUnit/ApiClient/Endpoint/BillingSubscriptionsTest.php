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
use WP_Error;
use function Brain\Monkey\Functions\when;

class BillingSubscriptionsTest extends TestCase
{
	private const HOST = 'https://api.sandbox.paypal.com';

	/**
	 * @var BillingSubscriptions
	 */
	private $endpoint;

	/**
	 * Arguments of the most recent outgoing HTTP request.
	 *
	 * @var array{url: string, args: array}|null
	 */
	private $captured_request;

	public function setUp(): void
	{
		parent::setUp();

		$this->captured_request = null;

		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('test_bearer_token_12345');
		$bearer = Mockery::mock(Bearer::class);
		$bearer->shouldReceive('bearer')->andReturn($token);

		$logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

		when('trailingslashit')->alias(static function ($value) {
			return rtrim($value, '/\\') . '/';
		});
		when('wp_remote_retrieve_response_code')->alias(static function ($response) {
			return $response['response']['code'] ?? 0;
		});
		when('wp_json_encode')->alias(static function ($data) {
			return \json_encode($data);
		});
		when('is_wp_error')->alias(static function ($thing) {
			return $thing instanceof WP_Error;
		});

		$this->endpoint = new BillingSubscriptions(self::HOST, $bearer, $logger);
	}

	/**
	 * GIVEN PayPal accepts the subscription request with a 201 response
	 * WHEN a subscription is created for a plan
	 * THEN the decoded subscription is returned, including its ID
	 */
	public function test_create_returns_subscription_when_paypal_responds_created(): void
	{
		$this->stub_response(201, ['id' => 'I-SUB123', 'status' => 'APPROVAL_PENDING']);

		$subscription = $this->endpoint->create('P-PLAN1', 'order-42');

		$this->assertSame('I-SUB123', $subscription->id);
		$this->assertSame('APPROVAL_PENDING', $subscription->status);
	}

	/**
	 * GIVEN a plan ID and a custom ID
	 * WHEN a subscription is created
	 * THEN a POST goes to the billing subscriptions endpoint with both IDs in the body
	 * AND a unique PayPal-Request-Id header guards against duplicate creation
	 */
	public function test_create_posts_plan_and_custom_id_with_request_id_header(): void
	{
		$this->stub_response(201, ['id' => 'I-SUB123']);

		$this->endpoint->create('P-PLAN1', 'order-42');

		$request = $this->captured_request;
		$this->assertSame(self::HOST . '/v1/billing/subscriptions', $request['url']);
		$this->assertSame('POST', $request['args']['method']);
		$this->assertSame(
			['plan_id' => 'P-PLAN1', 'custom_id' => 'order-42'],
			\json_decode($request['args']['body'], true)
		);
		$this->assertSame('Bearer test_bearer_token_12345', $request['args']['headers']['Authorization']);
		$this->assertNotEmpty($request['args']['headers']['PayPal-Request-Id']);
	}

	/**
	 * GIVEN no custom ID is available
	 * WHEN a subscription is created with an empty custom ID
	 * THEN the request body only contains the plan ID
	 */
	public function test_create_omits_custom_id_from_body_when_empty(): void
	{
		$this->stub_response(201, ['id' => 'I-SUB123']);

		$this->endpoint->create('P-PLAN1', '');

		$this->assertSame(
			['plan_id' => 'P-PLAN1'],
			\json_decode($this->captured_request['args']['body'], true)
		);
	}

	/**
	 * GIVEN the HTTP request fails before reaching PayPal
	 * WHEN a subscription is created
	 * THEN a RuntimeException is thrown
	 */
	public function test_create_throws_runtime_exception_when_request_fails(): void
	{
		$error = Mockery::mock(WP_Error::class);
		$error->shouldReceive('get_error_messages')->andReturn(['cURL error 28']);
		when('wp_remote_get')->justReturn($error);

		$this->expectException(RuntimeException::class);

		$this->endpoint->create('P-PLAN1', 'order-42');
	}

	/**
	 * GIVEN PayPal answers with an unexpected response
	 * WHEN a subscription is created
	 * THEN a PayPalApiException is thrown
	 *
	 * @dataProvider invalid_response_provider
	 */
	public function test_create_throws_api_exception_when_response_is_not_a_created_subscription(int $status, array $body): void
	{
		$this->stub_response($status, $body);

		$this->expectException(PayPalApiException::class);

		$this->endpoint->create('P-PLAN1', 'order-42');
	}

	public function invalid_response_provider(): array
	{
		return [
			'non-201 status with error details'      => [422, ['name' => 'UNPROCESSABLE_ENTITY', 'message' => 'Invalid plan']],
			'200 status instead of created'          => [200, ['id' => 'I-SUB123']],
			'201 status without a subscription ID'   => [201, ['status' => 'APPROVAL_PENDING']],
			'201 status with an empty subscription ID' => [201, ['id' => '']],
		];
	}

	private function stub_response(int $status, array $body): void
	{
		$response = [
			'response' => ['code' => $status],
			'body'     => \json_encode($body),
			'headers'  => new class {
				public function getAll(): array
				{
					return [];
				}
			},
		];

		when('wp_remote_get')->alias(function (string $url, array $args) use ($response) {
			$this->captured_request = ['url' => $url, 'args' => $args];
			return $response;
		});
	}
}
