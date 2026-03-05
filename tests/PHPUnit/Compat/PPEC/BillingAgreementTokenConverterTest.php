<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Mockery;
use Psr\Log\LoggerInterface;
use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class BillingAgreementTokenConverterTest extends TestCase
{
	/**
	 * @var PaymentMethodTokensEndpoint|Mockery\MockInterface
	 */
	private $payment_method_tokens_endpoint;

	/**
	 * @var CustomerRepository|Mockery\MockInterface
	 */
	private $customer_repository;

	/**
	 * @var LoggerInterface|Mockery\MockInterface
	 */
	private $logger;

	private BillingAgreementTokenConverter $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->payment_method_tokens_endpoint = Mockery::mock(PaymentMethodTokensEndpoint::class);
		$this->customer_repository            = Mockery::mock(CustomerRepository::class);
		$this->logger                         = Mockery::mock(LoggerInterface::class);

		$this->sut = new BillingAgreementTokenConverter(
			$this->payment_method_tokens_endpoint,
			$this->customer_repository,
			$this->logger
		);
	}

	public function testSuccessfulConversion()
	{
		$billing_agreement_id = 'B-ABC123';
		$user_id              = 42;
		$customer_id          = 'customer_42';
		$vault_token_id       = 'vault-token-xyz';

		$this->customer_repository
			->shouldReceive('customer_id_for_user')
			->with($user_id)
			->andReturn($customer_id);

		$api_result               = new stdClass();
		$api_result->id           = $vault_token_id;
		$api_result->customer     = new stdClass();
		$api_result->customer->id = 'paypal-customer-id';

		$this->payment_method_tokens_endpoint
			->shouldReceive('create_payment_token')
			->with(Mockery::type(PaymentSource::class), $customer_id)
			->andReturn($api_result);

		expect('update_user_meta')
			->once()
			->with($user_id, '_ppcp_target_customer_id', 'paypal-customer-id');

		$this->logger->shouldReceive('info')->once();

		$result = $this->sut->convert($billing_agreement_id, $user_id);

		$this->assertSame($vault_token_id, $result);
	}

	public function testSuccessfulConversionWithoutCustomerId()
	{
		$billing_agreement_id = 'B-ABC123';
		$user_id              = 42;
		$customer_id          = 'customer_42';
		$vault_token_id       = 'vault-token-xyz';

		$this->customer_repository
			->shouldReceive('customer_id_for_user')
			->with($user_id)
			->andReturn($customer_id);

		$api_result     = new stdClass();
		$api_result->id = $vault_token_id;

		$this->payment_method_tokens_endpoint
			->shouldReceive('create_payment_token')
			->andReturn($api_result);

		$this->logger->shouldReceive('info')->once();

		$result = $this->sut->convert($billing_agreement_id, $user_id);

		$this->assertSame($vault_token_id, $result);
	}

	public function testApiFailureReturnsNull()
	{
		$billing_agreement_id = 'B-ABC123';
		$user_id              = 42;
		$customer_id          = 'customer_42';

		$this->customer_repository
			->shouldReceive('customer_id_for_user')
			->with($user_id)
			->andReturn($customer_id);

		$this->payment_method_tokens_endpoint
			->shouldReceive('create_payment_token')
			->andThrow(new RuntimeException('Not able to create setup token.'));

		$this->logger->shouldReceive('error')->once();

		$result = $this->sut->convert($billing_agreement_id, $user_id);

		$this->assertNull($result);
	}

	public function testRuntimeErrorReturnsNull()
	{
		$billing_agreement_id = 'B-ABC123';
		$user_id              = 42;

		$this->customer_repository
			->shouldReceive('customer_id_for_user')
			->with($user_id)
			->andThrow(new \Exception('Unexpected error'));

		$this->logger->shouldReceive('error')->once();

		$result = $this->sut->convert($billing_agreement_id, $user_id);

		$this->assertNull($result);
	}
}
