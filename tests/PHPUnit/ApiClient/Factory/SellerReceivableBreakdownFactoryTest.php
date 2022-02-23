<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\TestCase;

class SellerReceivableBreakdownFactoryTest extends TestCase
{
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->testee = new SellerReceivableBreakdownFactory(
			new MoneyFactory(),
			new ExchangeRateFactory(),
			new PlatformFeeFactory(new MoneyFactory(), new PayeeFactory())
		);
	}

    /**
     * @dataProvider dataForTestFromPayPalResponse
     */
    public function testFromPayPalResponse(string $json, array $expected_result)
    {
		$obj = json_decode($json);

		$result = $this->testee->from_paypal_response($obj);

		self::assertEquals($expected_result, $result->to_array());
    }

    public function dataForTestFromPayPalResponse() : array
    {
		return [
			'fee' => [
				'
                {
                    "gross_amount": {
                        "currency_code": "USD",
                        "value": "10.42"
                    },
                    "paypal_fee": {
                        "currency_code": "USD",
                        "value": "0.41"
                    },
                    "net_amount": {
                        "currency_code": "USD",
                        "value": "10.01"
                    }
                }',
				[
					'gross_amount' => [
						'currency_code' => 'USD',
						'value' => '10.42',
					],
					'paypal_fee' => [
						'currency_code' => 'USD',
						'value' => '0.41',
					],
					'net_amount' => [
						'currency_code' => 'USD',
						'value' => '10.01',
					],
				],
			],
			'min' => [
				'
                {
                    "gross_amount": {
                        "currency_code": "USD",
                        "value": "10.42"
                    }
                }',
				[
					'gross_amount' => [
						'currency_code' => 'USD',
						'value' => '10.42',
					],
				],
			],
			'exchange' => [
				'
                {
                    "gross_amount": {
                        "value": "10.99",
                        "currency_code": "USD"
                    },
                    "paypal_fee": {
                        "value": "0.33",
                        "currency_code": "USD"
                    },
                    "net_amount": {
                        "value": "10.66",
                        "currency_code": "USD"
                    },
                    "receivable_amount": {
                        "currency_code": "CNY",
                        "value": "59.26"
                    },
                    "paypal_fee_in_receivable_currency": {
                        "currency_code": "CNY",
                        "value": "1.13"
                    },
                    "exchange_rate": {
                        "source_currency": "USD",
                        "target_currency": "CNY",
                        "value": "5.9483297432325"
                    }
                }',
				[
					'gross_amount' => [
						'currency_code' => 'USD',
						'value' => '10.99',
					],
					'paypal_fee' => [
						'currency_code' => 'USD',
						'value' => '0.33',
					],
					'net_amount' => [
						'currency_code' => 'USD',
						'value' => '10.66',
					],
					'receivable_amount' => [
						'currency_code' => 'CNY',
						'value' => '59.26',
					],
					'paypal_fee_in_receivable_currency' => [
						'currency_code' => 'CNY',
						'value' => '1.13',
					],
					'exchange_rate' => [
						'source_currency' => 'USD',
						'target_currency' => 'CNY',
						'value' => '5.9483297432325',
					],
				],
			],
			'platform_fees' => [
				'
                {
                    "gross_amount": {
                        "currency_code": "USD",
                        "value": "10.42"
                    },
                    "platform_fees": [
                        {
                            "amount": {
                                "currency_code": "USD",
                                "value": "0.06"
                            }
                        },
                        {
                            "amount": {
                                "currency_code": "USD",
                                "value": "0.08"
                            },
                            "payee": {
                                "email_address": "example@gmail.com"
                            }
                        }
                    ]
                }',
				[
					'gross_amount' => [
						'currency_code' => 'USD',
						'value' => '10.42',
					],
					'platform_fees' => [
						[
							'amount' => [
								'currency_code' => 'USD',
								'value' => '0.06',
							],
						],
						[
							'amount' => [
								'currency_code' => 'USD',
								'value' => '0.08',
							],
							'payee' => [
								'email_address' => 'example@gmail.com',
							],
						],
					],
				],
			],
		];
    }
}
