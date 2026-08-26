<?php
declare(strict_types=1);

namespace PHPUnit\ApiClient\Factory;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PartnersEndpointFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayPalBearerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerStatusFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\EnvironmentConfig;

class PartnersEndpointFactoryTest extends TestCase
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

	private function sut(
		EnvironmentConfig $paypal_host,
		EnvironmentConfig $partner_id,
		PayPalBearerFactory $bearer_factory
	): PartnersEndpointFactory {
		return new PartnersEndpointFactory(
			$paypal_host,
			$partner_id,
			Mockery::mock(SellerStatusFactory::class),
			Mockery::mock(FailureRegistry::class),
			Mockery::mock(Cache::class),
			$bearer_factory,
			$this->logger
		);
	}

	/**
	 * GIVEN merchant credentials and a per-environment host resolved from the environment config
	 * WHEN create() is called for either the sandbox or the live environment
	 * THEN it returns a PartnersEndpoint instance
	 * AND it delegates to the bearer factory with the resolved host and given credentials
	 *
	 * @dataProvider environment_provider
	 */
	public function testCreateReturnsPartnersEndpointAndForcesFullApiBearer(bool $is_sandbox, string $expected_host): void
	{
		$paypal_host = Mockery::mock(EnvironmentConfig::class);
		$paypal_host->shouldReceive('get_value')->with($is_sandbox)->andReturn($expected_host);

		$partner_id = Mockery::mock(EnvironmentConfig::class);
		$partner_id->shouldReceive('get_value')->with($is_sandbox)->andReturn('partner-id');

		$bearer = Mockery::mock(Bearer::class);
		$bearer_factory = Mockery::mock(PayPalBearerFactory::class);
		$bearer_factory->shouldReceive('create')
			->once()
			->with($expected_host, 'client-id', 'client-secret')
			->andReturn($bearer);

		$result = $this->sut($paypal_host, $partner_id, $bearer_factory)->create(
			$is_sandbox,
			'client-id',
			'client-secret',
			'merchant-id'
		);

		$this->assertInstanceOf(PartnersEndpoint::class, $result);
	}

	public function environment_provider(): array
	{
		return [
			'sandbox environment resolves sandbox host' => [true, 'https://api-m.sandbox.paypal.com'],
			'live environment resolves live host' => [false, 'https://api-m.paypal.com'],
		];
	}
}
