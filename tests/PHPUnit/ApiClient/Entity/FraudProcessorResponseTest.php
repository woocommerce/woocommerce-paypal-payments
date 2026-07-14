<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use function Brain\Monkey\Functions\expect;

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

	public function testGetCustomerDeclineMessageWithKnownCodeIsUserFriendly(): void
	{
		$testee = new FraudProcessorResponse(null, null, '9500');
		$this->assertSame(
			'Your payment could not be processed because your bank was unable to approve this transaction. Please try a different payment method or contact your bank for more information.',
			$testee->get_customer_decline_message()
		);
		$this->assertStringNotContainsString('9500', $testee->get_customer_decline_message());
		$this->assertStringNotContainsString('Fraud', $testee->get_customer_decline_message());
	}

	public function testGetCustomerDeclineMessageWithUnknownCodeUsesDefaultReason(): void
	{
		$testee = new FraudProcessorResponse(null, null, 'ZZZZ');
		$this->assertSame(
			'Your payment could not be processed because your card was declined by your bank. Please try a different payment method or contact your bank for more information.',
			$testee->get_customer_decline_message()
		);
	}

	public function testGetCustomerDeclineMessageWithEmptyCodeReturnsGenericMessage(): void
	{
		$testee = new FraudProcessorResponse(null, null, null);
		$this->assertSame(
			'Payment provider declined the payment, please use a different payment method.',
			$testee->get_customer_decline_message()
		);
	}

	public function testCustomerDeclineMessageFilterOverridesFinalMessage(): void
	{
		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_customer_decline_reason_message', Mockery::any(), '5120', Mockery::any())
			->andReturnUsing(function ($hook, $value) {
				return $value;
			});

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_customer_decline_message', Mockery::any(), Mockery::any())
			->andReturn('Custom decline message.');

		$testee = new FraudProcessorResponse(null, null, '5120');
		$this->assertSame('Custom decline message.', $testee->get_customer_decline_message());
	}

	public function testCustomerDeclineReasonMessageFilterOverridesReason(): void
	{
		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_customer_decline_reason_message', Mockery::any(), '5120', Mockery::any())
			->andReturn('a custom reason.');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_customer_decline_message', Mockery::any(), Mockery::any())
			->andReturnUsing(function ($hook, $value) {
				return $value;
			});

		$testee = new FraudProcessorResponse(null, null, '5120');
		$this->assertSame(
			'Your payment could not be processed because a custom reason. Please try a different payment method or contact your bank for more information.',
			$testee->get_customer_decline_message()
		);
	}
}