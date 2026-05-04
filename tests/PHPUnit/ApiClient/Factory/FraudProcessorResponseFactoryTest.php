<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\FraudProcessorResponse;
use WooCommerce\PayPalCommerce\TestCase;

class FraudProcessorResponseFactoryTest extends TestCase
{
	public function testFromPaypalResponseParsesAllFields(): void
	{
		$data = (object) [
			'avs_code'      => 'Y',
			'cvv_code'      => 'M',
			'response_code' => '9500',
		];

		$testee = new FraudProcessorResponseFactory();
		$result = $testee->from_paypal_response($data);

		$this->assertInstanceOf(FraudProcessorResponse::class, $result);
		$this->assertSame('Y', $result->avs_code());
		$this->assertSame('M', $result->cvv_code());
		$this->assertSame('9500', $result->response_code());
	}

	public function testFromPaypalResponseWithMissingResponseCode(): void
	{
		$data = (object) [
			'avs_code' => 'Y',
			'cvv_code' => 'M',
		];

		$testee = new FraudProcessorResponseFactory();
		$result = $testee->from_paypal_response($data);

		$this->assertSame('', $result->response_code());
	}

	public function testFromPaypalResponseWithEmptyResponseCode(): void
	{
		$data = (object) [
			'avs_code'      => 'Y',
			'cvv_code'      => 'M',
			'response_code' => '',
		];

		$testee = new FraudProcessorResponseFactory();
		$result = $testee->from_paypal_response($data);

		// Empty string is falsy; the factory coerces it to null, stored as empty string.
		$this->assertSame('', $result->response_code());
	}
}