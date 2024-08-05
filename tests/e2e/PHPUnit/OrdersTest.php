<?php

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;

class OrdersTest extends TestCase
{
	public function test_create()
	{
		$host = 'https://api-m.sandbox.paypal.com';
		$container = $this->getContainer();

		$orders = new Orders($host, $container->get('api.bearer'));

		$requestBody = [
			"intent" => "CAPTURE",
			"payment_source" => [
				"bancontact" => [
					"country_code" => "BE",
					"name" => "John Doe"
				]
			],
			"processing_instruction" => "ORDER_COMPLETE_ON_PAYMENT_APPROVAL",
			"purchase_units" => [
				[
					"reference_id" => "d9f80740-38f0-11e8-b467-0ed5f89f718b",
					"amount" => [
						"currency_code" => "EUR",
						"value" => "1.00"
					],
				]
			],
			"application_context" => [
				"locale" => "en-BE",
				"return_url" => "https://example.com/returnUrl",
				"cancel_url" => "https://example.com/cancelUrl"
			]
		];

		$headers = array(
			'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
		);

		$result = $orders->create($requestBody, $headers);

		$this->assertEquals(200, $result['response']['code']);
	}
}
