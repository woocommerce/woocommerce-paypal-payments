<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;

class PaymentTokenActionLinksFactoryTest extends TestCase
{
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->testee = new PaymentTokenActionLinksFactory();
	}

    /**
     * @dataProvider validData
     */
    public function testSuccess(string $json, string $approve_link, string $confirm_link, string $status_link)
    {
		$obj = json_decode($json);

		$result = $this->testee->from_paypal_response($obj);

		self::assertEquals($approve_link, $result->approve_link());
		self::assertEquals($confirm_link, $result->confirm_link());
		self::assertEquals($status_link, $result->status_link());
    }

    /**
     * @dataProvider invalidData
     */
    public function testFailure(string $json)
    {
		$obj = json_decode($json);

		$this->expectException(RuntimeException::class);

		$this->testee->from_paypal_response($obj);
    }

    public function validData() : array
    {
		return [
			[
				'
				{
				  "links": [
					{
					  "href": "https://www.sandbox.paypal.com/webapps/agreements/approve?approval_session_id=qwe123",
					  "rel": "approve",
					  "method": "POST"
					},
					{
					  "href": "https://api-m.sandbox.paypal.com/v2/vault/approval-tokens/asd123/confirm-payment-token",
					  "rel": "confirm",
					  "method": "POST"
					},
					{
					  "href": "https://api-m.sandbox.paypal.com/v2/vault/approval-tokens/asd123",
					  "rel": "status",
					  "method": "GET"
					}
				  ]
				}
				',
				'https://www.sandbox.paypal.com/webapps/agreements/approve?approval_session_id=qwe123',
				'https://api-m.sandbox.paypal.com/v2/vault/approval-tokens/asd123/confirm-payment-token',
				'https://api-m.sandbox.paypal.com/v2/vault/approval-tokens/asd123',
			],
			[
				'
				{
				  "links": [
					{
					  "href": "https://www.sandbox.paypal.com/webapps/agreements/approve?approval_session_id=qwe123",
					  "rel": "approve",
					  "method": "POST"
					}
				  ]
				}
				',
				'https://www.sandbox.paypal.com/webapps/agreements/approve?approval_session_id=qwe123',
				'',
				'',
			],
			[
				'
				{
				  "links": [
					{
					  "href": "https://example.com",
					  "rel": "new",
					  "method": "POST"
					},
					{
					  "href": "https://www.sandbox.paypal.com/webapps/agreements/approve?approval_session_id=qwe123",
					  "rel": "approve",
					  "method": "POST"
					}
				  ]
				}
				',
				'https://www.sandbox.paypal.com/webapps/agreements/approve?approval_session_id=qwe123',
				'',
				'',
			],
		];
    }

    public function invalidData() : array
    {
		return [
			[
				'
				{
				  "links": [
					{}
				  ]
				}
				',
				'
				{
				  "links": []
				}
				',
				'{}',
				'
				{
				  "links": [
					{},
					{
					  "href": "https://example.com",
					  "rel": "new",
					  "method": "POST"
					}
				  ]
				}
				',
				'no approve link' => '
				{
				  "links": [
					{
					  "href": "https://api-m.sandbox.paypal.com/v2/vault/approval-tokens/asd123/confirm-payment-token",
					  "rel": "confirm",
					  "method": "POST"
					}
				  ]
				}
				',
			],
		];
    }
}
