<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;

class FraudProcessorResponseTest extends TestCase
{
	public function testResponseCodeGetter(): void
	{
		$testee = new FraudProcessorResponse('A', 'M', '9500');
		$this->assertSame('9500', $testee->response_code());
	}

	public function testNullResponseCodeStoredAsEmptyString(): void
	{
		$testee = new FraudProcessorResponse('A', 'M', null);
		$this->assertSame('', $testee->response_code());
	}

	public function testToArrayIncludesResponseCode(): void
	{
		$testee = new FraudProcessorResponse('A', 'M', '9500');
		$array  = $testee->to_array();

		$this->assertArrayHasKey('response_code', $array);
		$this->assertSame('9500', $array['response_code']);
	}

	public function testToArrayWithoutResponseCodeHasEmptyString(): void
	{
		$testee = new FraudProcessorResponse('A', 'M', null);
		$array  = $testee->to_array();

		$this->assertArrayHasKey('response_code', $array);
		$this->assertSame('', $array['response_code']);
	}

	public function testGetResponseCodeMessageForKnownCode9500(): void
	{
		$testee = new FraudProcessorResponse(null, null, '9500');
		$this->assertSame('9500: Suspected Fraud', $testee->get_response_code_message());
	}

	public function testGetResponseCodeMessageForKnownCode0000(): void
	{
		$testee = new FraudProcessorResponse(null, null, '0000');
		$this->assertSame('0000: Approved', $testee->get_response_code_message());
	}

	public function testGetResponseCodeMessageForKnownCode9100(): void
	{
		$testee = new FraudProcessorResponse(null, null, '9100');
		$this->assertSame('9100: Declined, Please Retry', $testee->get_response_code_message());
	}

	public function testGetResponseCodeMessageForUnknownCode(): void
	{
		$testee = new FraudProcessorResponse(null, null, 'ZZZZ');
		$this->assertSame('ZZZZ: Unknown response code', $testee->get_response_code_message());
	}

	public function testGetResponseCodeMessageForEmptyCodeReturnsEmptyString(): void
	{
		$testee = new FraudProcessorResponse(null, null, null);
		$this->assertSame('', $testee->get_response_code_message());
	}
}