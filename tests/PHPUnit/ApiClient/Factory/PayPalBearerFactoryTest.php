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
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

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

	private function sut(ConnectionState $connection_state): PayPalBearerFactory
	{
		$rate_limiter = Mockery::mock(TokenRateLimiter::class);

		return new PayPalBearerFactory($connection_state, $rate_limiter, $this->logger);
	}

	/**
	 * GIVEN a store with no explicit connection override
	 * WHEN create() is called and the ambient connection state resolves to a given value
	 * THEN it returns a PayPalBearer while connected, otherwise a login-only ConnectBearer
	 *
	 * @dataProvider ambientConnectionData
	 */
	public function testCreateWithoutOverrideUsesAmbientConnectionState(bool $ambientConnected, string $expectedClass): void
	{
		$connection_state = Mockery::mock(ConnectionState::class);
		$connection_state->shouldReceive('is_connected')->andReturn($ambientConnected);

		$result = $this->sut($connection_state)->create('https://example.com', 'client-id', 'client-secret');

		$this->assertInstanceOf($expectedClass, $result);
	}

	public function ambientConnectionData(): array
	{
		return [
			'ambient connected returns PayPalBearer' => [true, PayPalBearer::class],
			'ambient disconnected returns ConnectBearer' => [false, ConnectBearer::class],
		];
	}

	/**
	 * GIVEN a store whose ambient connection state is irrelevant
	 * WHEN create() is called with an explicit connection override
	 * THEN it returns the bearer matching the override
	 * AND it never consults the ambient connection state
	 *
	 * @dataProvider overrideConnectionData
	 */
	public function testCreateWithOverrideShortCircuitsAmbientConnectionState(bool $override, string $expectedClass): void
	{
		$connection_state = Mockery::mock(ConnectionState::class);
		$connection_state->shouldReceive('is_connected')->never();

		$result = $this->sut($connection_state)->create(
			'https://example.com',
			'client-id',
			'client-secret',
			$override
		);

		$this->assertInstanceOf($expectedClass, $result);
	}

	public function overrideConnectionData(): array
	{
		return [
			'override true returns PayPalBearer' => [true, PayPalBearer::class],
			'override false returns ConnectBearer' => [false, ConnectBearer::class],
		];
	}

	/**
	 * GIVEN a store that is connected, with an explicit cache and settings provider
	 * WHEN create() is called
	 * THEN it still returns a PayPalBearer, passing both through to the constructor
	 */
	public function testCreateWhenConnectedWithExplicitCacheAndSettingsReturnsPayPalBearer(): void
	{
		$connection_state = Mockery::mock(ConnectionState::class);
		$connection_state->shouldReceive('is_connected')->never();

		$cache = Mockery::mock(Cache::class);
		$settings = Mockery::mock(SettingsProvider::class);

		$result = $this->sut($connection_state)->create(
			'https://example.com',
			'client-id',
			'client-secret',
			true,
			$cache,
			$settings
		);

		$this->assertInstanceOf(PayPalBearer::class, $result);
	}
}
