<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Validation\CheckoutFormValidator;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class ValidateCheckoutEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private $requestData;
	private $formValidator;
	private $logger;
	private $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->requestData = Mockery::mock(RequestData::class);
		$this->formValidator = Mockery::mock(CheckoutFormValidator::class);
		$this->logger = Mockery::mock(LoggerInterface::class);

		$this->sut = new ValidateCheckoutEndpoint(
			$this->requestData,
			$this->formValidator,
			$this->logger
		);

		$this->requestData->expects('read_request')->andReturn(['form' => ['field1' => 'value']]);
	}

	public function testValid()
	{
		$this->formValidator->expects('validate')->once();

		expect('wp_send_json_success')->once();

		$this->sut->handle_request();
	}

	public function testInvalid()
	{
		$exception = new ValidationException(['Invalid value']);
		$this->formValidator->expects('validate')->once()
			->andThrow($exception);

		expect('wp_send_json_error')->once()
			->with(['message' => $exception->getMessage(), 'errors' => ['Invalid value']]);

		$this->sut->handle_request();
	}

	public function testFailure()
	{
		$exception = new Exception('BOOM');
		$this->formValidator->expects('validate')->once()
			->andThrow($exception);

		expect('wp_send_json_error')->once()
			->with(['message' => $exception->getMessage()]);

		$this->logger->expects('error')->once();

		$this->sut->handle_request();
	}
}
