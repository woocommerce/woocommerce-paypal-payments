<?php

namespace WooCommerce\PayPalCommerce\ApiClient\Exception;

use WooCommerce\PayPalCommerce\TestCase;

class PayPalApiExceptionTest extends TestCase
{
	public function testFriendlyMessage()
	{
		$testee = new PayPalApiException();

		$response = json_decode('{"details":[{"issue":"PAYMENT_DENIED"}]}');

		$this->assertEquals(
			'PayPal rejected the payment. Please reach out to the PayPal support for more information.',
			$testee->get_customer_friendly_message($response)
		);
	}
}
