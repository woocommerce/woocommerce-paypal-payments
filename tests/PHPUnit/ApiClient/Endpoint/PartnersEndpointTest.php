<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerStatusFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class PartnersEndpointTest extends TestCase
{
	private const HOST        = 'https://api.sandbox.paypal.com/';
	private const PARTNER_ID  = 'partner-abc';
	private const MERCHANT_ID = 'merchant-xyz';

	/**
	 * @var Bearer&\Mockery\MockInterface
	 */
	private $bearer;

	/**
	 * @var LoggerInterface&\Mockery\MockInterface
	 */
	private $logger;

	/**
	 * @var SellerStatusFactory&\Mockery\MockInterface
	 */
	private $seller_status_factory;

	/**
	 * @var FailureRegistry&\Mockery\MockInterface
	 */
	private $failure_registry;

	/**
	 * @var Cache&\Mockery\MockInterface
	 */
	private $cache;

	public function setUp(): void
	{
		parent::setUp();

		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('test-token');

		$this->bearer = Mockery::mock(Bearer::class);
		$this->bearer->shouldReceive('bearer')->andReturn($token);

		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->logger->shouldReceive('debug')->andReturnNull();
		$this->logger->shouldReceive('log')->andReturnNull();

		$this->seller_status_factory = Mockery::mock(SellerStatusFactory::class);
		$this->failure_registry      = Mockery::mock(FailureRegistry::class);
		$this->cache                 = Mockery::mock(Cache::class);

		when('trailingslashit')->alias(function (string $url): string {
			return rtrim($url, '/') . '/';
		});
		when('wp_remote_retrieve_body')->alias(function (array $response): string {
			return $response['body'] ?? '';
		});
	}

	// -----------------------------------------------------------------------
	// Factory helper
	// -----------------------------------------------------------------------

	private function make_endpoint(): PartnersEndpoint
	{
		return new PartnersEndpoint(
			self::HOST,
			$this->bearer,
			$this->logger,
			$this->seller_status_factory,
			self::PARTNER_ID,
			self::MERCHANT_ID,
			$this->failure_registry,
			$this->cache
		);
	}

	private function make_raw_response(int $status_code = 200): array
	{
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll')->andReturn([]);

		return [
			'body'     => '{}',
			'headers'  => $headers,
			'response' => ['code' => $status_code, 'message' => 'OK'],
		];
	}

	// -----------------------------------------------------------------------
	// Tests: seller_status() caching behaviour
	// -----------------------------------------------------------------------

	/**
	 * GIVEN a SellerStatus result is stored in the transient cache
	 * WHEN seller_status() is called
	 * THEN the cached value is returned immediately
	 * AND no HTTP request is made to the PayPal API
	 */
	public function testSellerStatusReturnsCachedValueWithoutHttpCall(): void
	{
		$cached_status = Mockery::mock(SellerStatus::class);

		$this->cache
			->shouldReceive('get')
			->once()
			->with(PartnersEndpoint::SELLER_STATUS_CACHE_KEY)
			->andReturn($cached_status);

		// wp_remote_get must not be called — any invocation will cause the
		// Brain Monkey expectation to fail with an unexpected call.
		expect('wp_remote_get')->never();

		$result = $this->make_endpoint()->seller_status();

		$this->assertSame($cached_status, $result);
	}

	/**
	 * GIVEN the transient cache contains no seller status entry
	 * WHEN seller_status() is called
	 * THEN the PayPal API is queried
	 * AND the response is stored in the cache with the correct key and TTL
	 * AND the resulting SellerStatus is returned
	 */
	public function testSellerStatusOnCacheMissFetchesAndStoresResult(): void
	{
		$seller_status = Mockery::mock(SellerStatus::class);
		$raw_response  = $this->make_raw_response(200);

		$this->cache
			->shouldReceive('get')
			->once()
			->with(PartnersEndpoint::SELLER_STATUS_CACHE_KEY)
			->andReturn(false);

		$this->cache
			->shouldReceive('set')
			->once()
			->with(
				PartnersEndpoint::SELLER_STATUS_CACHE_KEY,
				$seller_status,
				PartnersEndpoint::SELLER_STATUS_CACHE_TTL
			);

		$this->seller_status_factory
			->shouldReceive('from_paypal_response')
			->once()
			->andReturn($seller_status);

		$this->failure_registry
			->shouldReceive('clear_failures')
			->once()
			->with(FailureRegistry::SELLER_STATUS_KEY);

		$this->stub_successful_http_round_trip($raw_response);

		$result = $this->make_endpoint()->seller_status();

		$this->assertSame($seller_status, $result);
	}

	/**
	 * GIVEN the transient cache is empty
	 * AND the PayPal API returns a non-200 status code
	 * WHEN seller_status() is called
	 * THEN the failure is registered in the failure registry
	 * AND the result is NOT stored in the cache
	 * AND a PayPalApiException is thrown
	 */
	public function testSellerStatusOnApiErrorRegistersFailureAndThrows(): void
	{
		$raw_response = $this->make_raw_response(500);

		$this->cache
			->shouldReceive('get')
			->once()
			->with(PartnersEndpoint::SELLER_STATUS_CACHE_KEY)
			->andReturn(false);

		$this->cache
			->shouldReceive('set')
			->never();

		$this->failure_registry
			->shouldReceive('add_failure')
			->once()
			->with(FailureRegistry::SELLER_STATUS_KEY);

		$this->stub_failed_http_round_trip($raw_response, 500);

		$this->expectException(PayPalApiException::class);

		$this->make_endpoint()->seller_status();
	}

	// -----------------------------------------------------------------------
	// Tests: clear_seller_status_cache()
	// -----------------------------------------------------------------------

	/**
	 * GIVEN a cached seller status entry exists
	 * WHEN clear_seller_status_cache() is called
	 * THEN the cache entry is deleted using the correct key
	 */
	public function testClearSellerStatusCacheDeletesCacheEntry(): void
	{
		$this->cache
			->shouldReceive('delete')
			->once()
			->with(PartnersEndpoint::SELLER_STATUS_CACHE_KEY);

		$this->make_endpoint()->clear_seller_status_cache();

		// Mockery will assert the expectation above during tearDown.
		$this->addToAssertionCount(1);
	}

	// -----------------------------------------------------------------------
	// HTTP stubs — allow (not assert) internal HTTP mechanics
	// -----------------------------------------------------------------------

	private function stub_successful_http_round_trip(array $raw_response): void
	{
		when('apply_filters')->alias(function (string $hook, ...$args) {
			return $args[0] ?? null;
		});
		when('wp_remote_get')->justReturn($raw_response);
		when('is_wp_error')->justReturn(false);
		when('wp_remote_retrieve_response_code')->justReturn(200);
	}

	private function stub_failed_http_round_trip(array $raw_response, int $status_code): void
	{
		when('apply_filters')->alias(function (string $hook, ...$args) {
			return $args[0] ?? null;
		});
		when('wp_remote_get')->justReturn($raw_response);
		when('is_wp_error')->justReturn(false);
		when('wp_remote_retrieve_response_code')->justReturn($status_code);
	}
}
