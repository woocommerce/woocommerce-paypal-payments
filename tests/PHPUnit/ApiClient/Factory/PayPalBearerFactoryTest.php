<?php
declare(strict_types=1);

namespace PHPUnit\ApiClient\Factory;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\TokenRateLimiter;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayPalBearerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;

class PayPalBearerFactoryTest extends TestCase
{
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function sut(): PayPalBearerFactory
	{
		$rate_limiter = Mockery::mock(TokenRateLimiter::class);

		return new PayPalBearerFactory($rate_limiter, $this->logger);
	}

	/**
	 * GIVEN a host and a set of client credentials
	 * WHEN create() is called
	 * THEN it returns a PayPalBearer only when both client ID and secret are present,
	 * otherwise a login-only ConnectBearer
	 *
	 * @dataProvider credentialPresenceData
	 */
	public function testCreateDecidesBearerTypeByCredentialPresence(string $clientId, string $clientSecret, string $expectedClass): void
	{
		$result = $this->sut()->create('https://example.com', $clientId, $clientSecret);

		$this->assertInstanceOf($expectedClass, $result);
	}

	public function credentialPresenceData(): array
	{
		return [
			'client id and secret present returns PayPalBearer' => ['client-id', 'client-secret', PayPalBearer::class],
			'empty client id returns ConnectBearer' => ['', 'client-secret', ConnectBearer::class],
			'empty client secret returns ConnectBearer' => ['client-id', '', ConnectBearer::class],
			'no credentials returns ConnectBearer' => ['', '', ConnectBearer::class],
		];
	}

	/**
	 * GIVEN non-empty client credentials, an explicit cache and settings provider
	 * WHEN create() is called
	 * THEN it still returns a PayPalBearer, passing both through to the constructor
	 */
	public function testCreateWithExplicitCacheAndSettingsReturnsPayPalBearer(): void
	{
		$cache = Mockery::mock(Cache::class);
		$settings = Mockery::mock(SettingsProvider::class);

		$result = $this->sut()->create(
			'https://example.com',
			'client-id',
			'client-secret',
			$cache,
			$settings
		);

		$this->assertInstanceOf(PayPalBearer::class, $result);
	}
}
