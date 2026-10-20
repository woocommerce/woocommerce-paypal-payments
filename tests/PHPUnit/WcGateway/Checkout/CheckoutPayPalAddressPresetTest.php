<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Checkout;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PayerName;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Phone;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PhoneWithType;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery\MockInterface;

class CheckoutPayPalAddressPresetTest extends TestCase
{
    private $mocks = [];

    public function tearDown(): void
    {
        $this->mocks = [];
        parent::tearDown();
    }

    /**
     * @dataProvider filterCheckoutFieldData
     */
    public function testFilterCheckoutField(string $fieldId, ?Order $order, ?string $expected): void
    {
        // SessionHandler
        $this->buildTestee()[0]->shouldReceive('order')
            ->andReturn($order);

        /* @var CheckoutPayPalAddressPreset $testee */
        $testee = $this->buildTestee()[1];

        self::assertSame(
            $expected,
            $testee->filter_checkout_field(null, $fieldId)
        );
    }

	/**
	 * @dataProvider filterCheckoutFieldDataNoAddress
	 */
	public function testFilterCheckoutFieldNoAddress(string $fieldId, ?Order $order, ?string $expected): void
	{
		$this->buildTestee()[0]->shouldReceive('order')
		                       ->andReturn($order);

		/* @var CheckoutPayPalAddressPreset $testee */
		$testee = $this->buildTestee()[1];

		self::assertSame(
			$expected,
			$testee->filter_checkout_field(null, $fieldId)
		);
	}

    /**
     * GIVEN a shopper who typed their own first name into the checkout form
     * WHEN the checkout page renders again after returning from PayPal
     * THEN the shopper's own entry is returned instead of the name on their PayPal account
     */
    public function testFilterCheckoutFieldPrefersShopperEnteredNameOverPayPalNameAfterReturnFromPayPal(): void
    {
        $order = $this->buildOrderWithPayerName('John', 'Doe');

        $this->buildTestee()[0]->shouldReceive('order')->andReturn($order);
        $this->buildTestee()[0]->shouldReceive('checkout_form')->andReturn(['billing_first_name' => 'Narek']);

        $testee = $this->buildTestee()[1];

        self::assertSame('Narek', $testee->filter_checkout_field(null, 'billing_first_name'));
    }

    /**
     * GIVEN the shopper's saved checkout form has an empty value for a field
     * WHEN the field is resolved
     * THEN the PayPal preset is used, because a blank string is not an answer
     */
    public function testFilterCheckoutFieldFallsBackToPresetWhenSavedValueIsBlank(): void
    {
        $order = $this->buildOrderWithPayerName('John', 'Doe');

        $this->buildTestee()[0]->shouldReceive('order')->andReturn($order);
        $this->buildTestee()[0]->shouldReceive('checkout_form')->andReturn(['billing_first_name' => '']);

        $testee = $this->buildTestee()[1];

        self::assertSame('John', $testee->filter_checkout_field(null, 'billing_first_name'));
    }

    /**
     * GIVEN an express checkout started from a product or cart page, where no checkout form was ever saved
     * WHEN a billing field is resolved
     * THEN the PayPal preset supplies the value, because there is nothing saved to prefer over it
     */
    public function testFilterCheckoutFieldFallsBackToPresetWhenNoCheckoutFormWasEverSaved(): void
    {
        $order = $this->buildOrderWithPayerName('John', 'Doe');

        $this->buildTestee()[0]->shouldReceive('order')->andReturn($order);
        $this->buildTestee()[0]->shouldReceive('checkout_form')->andReturn([]);

        $testee = $this->buildTestee()[1];

        self::assertSame('John', $testee->filter_checkout_field(null, 'billing_first_name'));
    }

    /**
     * GIVEN a saved checkout form value that is not a string
     * WHEN the field is resolved
     * THEN the PayPal preset is used rather than returning the non-string value
     *
     * @dataProvider nonStringSavedCheckoutValueProvider
     */
    public function testFilterCheckoutFieldFallsBackToPresetWhenSavedValueIsNotAString($savedValue): void
    {
        $order = $this->buildOrderWithPayerName('John', 'Doe');

        $this->buildTestee()[0]->shouldReceive('order')->andReturn($order);
        $this->buildTestee()[0]->shouldReceive('checkout_form')->andReturn(['billing_first_name' => $savedValue]);

        $testee = $this->buildTestee()[1];

        self::assertSame('John', $testee->filter_checkout_field(null, 'billing_first_name'));
    }

    /**
     * @see testFilterCheckoutFieldFallsBackToPresetWhenSavedValueIsNotAString
     */
    public function nonStringSavedCheckoutValueProvider(): array
    {
        return [
            'array value' => [['unexpected']],
            'boolean value' => [true],
            'integer value' => [123],
        ];
    }

    /**
     * GIVEN a non-string field ID
     * WHEN the field is resolved
     * THEN the default value is returned untouched, without consulting the saved form or the PayPal preset
     */
    public function testFilterCheckoutFieldReturnsDefaultValueWhenFieldIdIsNotAString(): void
    {
        $this->buildTestee()[0]->shouldReceive('order')->never();
        $this->buildTestee()[0]->shouldReceive('checkout_form')->never();

        $testee = $this->buildTestee()[1];

        self::assertSame('fallback', $testee->filter_checkout_field('fallback', 123));
    }

    /**
     * Builds an Order whose PayPal payer has the given name and no shipping address,
     * for tests that only care about the shopper's-own-form-vs-preset precedence.
     */
    private function buildOrderWithPayerName(string $givenName, string $surname): Order
    {
        return \Mockery::mock(
            Order::class,
            [
                'id' => 'order-with-payer-name',
                'purchase_units' => [],
                'payer' => \Mockery::mock(
                    Payer::class,
                    [
                        'name' => \Mockery::mock(
                            PayerName::class,
                            [
                                'given_name' => $givenName,
                                'surname' => $surname,
                            ]
                        ),
                    ]
                ),
            ]
        );
    }

    /**
     * @see testFilterCheckoutField
     */
    public function filterCheckoutFieldData(): array
    {
        $order = \Mockery::mock(
            Order::class,
            [
                'id' => 'abc123def',
                'purchase_units' => [
                    \Mockery::mock(
                        PurchaseUnit::class,
                        [
                            'shipping' => \Mockery::mock(
                                Shipping::class,
                                [
                                    'address' => \Mockery::mock(
                                        Address::class,
                                        [
                                            'address_line_1' => 'Unter den Linden 1',
                                            'address_line_2' => '2. Stock Hinterhaus',
                                            'postal_code' => '10117',
                                            'country_code' => 'DE',
                                            'admin_area_1' => 'BE',
                                            'admin_area_2' => 'Berlin',
                                        ]
                                    ),
                                ]
                            ),
                        ]
                    ),
                ],
                'payer' => \Mockery::mock(
                    Payer::class,
                    [
                        'name' => \Mockery::mock(
                            PayerName::class,
                            [
                                'given_name' => 'John',
                                'surname' => 'Doe',
                            ]
                        ),
                        'email_address' => 'mail@domain.tld',
                        'phone' => \Mockery::mock(
                            PhoneWithType::class,
                            [
                                'phone' => \Mockery::mock(
                                    Phone::class,
                                    [
                                        'national_number' => '+4912345678',
                                    ]
                                ),
                            ]
                        ),
                    ]
                ),
            ]
        );

        return [
            'Test billing_address_1' => [
                'fieldId' => 'billing_address_1',
                'order' => $order,
                'expected' => 'Unter den Linden 1',
            ],
            'Test billing_address_2' => [
                'fieldId' => 'billing_address_2',
                'order' => $order,
                'expected' => '2. Stock Hinterhaus',
            ],
            'Test billing_postcode' => [
                'fieldId' => 'billing_postcode',
                'order' => $order,
                'expected' => '10117',
            ],
            'Test billing_country' => [
                'fieldId' => 'billing_country',
                'order' => $order,
                'expected' => 'DE',
            ],
            'Test billing_city' => [
                'fieldId' => 'billing_city',
                'order' => $order,
                'expected' => 'Berlin',
            ],
            'Test billing_state' => [
                'fieldId' => 'billing_state',
                'order' => $order,
                'expected' => 'BE',
            ],
            'Test billing_last_name' => [
                'fieldId' => 'billing_last_name',
                'order' => $order,
                'expected' => 'Doe',
            ],
            'Test billing_first_name' => [
                'fieldId' => 'billing_first_name',
                'order' => $order,
                'expected' => 'John',
            ],
            'Test billing_email' => [
                'fieldId' => 'billing_email',
                'order' => $order,
                'expected' => 'mail@domain.tld',
            ],
            'Test billing_phone' => [
                'fieldId' => 'billing_phone',
                'order' => $order,
                'expected' => '+4912345678',
            ],
        ];
    }

	/**
	 * @see testFilterCheckoutFieldNoAddress
	 */
	public function filterCheckoutFieldDataNoAddress(): array
	{
		$order = \Mockery::mock(
			Order::class,
			[
				'id' => 'abc123def',
				'purchase_units' => [
					\Mockery::mock(
						PurchaseUnit::class,
						[
							'shipping' => \Mockery::mock(
								Shipping::class,
								[
									'address' => null,
								]
							),
						]
					),
				],
				'payer' => \Mockery::mock(
					Payer::class,
					[
						'name' => \Mockery::mock(
							PayerName::class,
							[
								'given_name' => 'John',
								'surname' => 'Doe',
							]
						),
						'email_address' => 'mail@domain.tld',
						'phone' => \Mockery::mock(
							PhoneWithType::class,
							[
								'phone' => \Mockery::mock(
									Phone::class,
									[
										'national_number' => '+4912345678',
									]
								),
							]
						),
					]
				),
			]
		);

		return [
			'Test billing_address_1' => [
				'fieldId' => 'billing_address_1',
				'order' => $order,
				'expected' => null,
			],
			'Test billing_address_2' => [
				'fieldId' => 'billing_address_2',
				'order' => $order,
				'expected' => null,
			],
			'Test billing_postcode' => [
				'fieldId' => 'billing_postcode',
				'order' => $order,
				'expected' => null,
			],
			'Test billing_country' => [
				'fieldId' => 'billing_country',
				'order' => $order,
				'expected' => null,
			],
			'Test billing_city' => [
				'fieldId' => 'billing_city',
				'order' => $order,
				'expected' => null,
			],
			'Test billing_state' => [
				'fieldId' => 'billing_state',
				'order' => $order,
				'expected' => null,
			],
			'Test billing_last_name' => [
				'fieldId' => 'billing_last_name',
				'order' => $order,
				'expected' => 'Doe',
			],
			'Test billing_first_name' => [
				'fieldId' => 'billing_first_name',
				'order' => $order,
				'expected' => 'John',
			],
			'Test billing_email' => [
				'fieldId' => 'billing_email',
				'order' => $order,
				'expected' => 'mail@domain.tld',
			],
			'Test billing_phone' => [
				'fieldId' => 'billing_phone',
				'order' => $order,
				'expected' => '+4912345678',
			],
		];
	}

    public function testReadShippingFromOrder(): void
    {
        $shipping = \Mockery::mock(Shipping::class);
        $purchaseUnit = \Mockery::mock(PurchaseUnit::class);
        $purchaseUnit->shouldReceive('shipping')
            ->once()
            ->andReturn($shipping);
        $purchaseUnitLast = \Mockery::mock(PurchaseUnit::class);
        $purchaseUnitLast->shouldReceive('shipping')
            ->never();
        $order = \Mockery::mock(
            Order::class,
            [
                'id' => 'whatever',
            ]
        );
        $order->shouldReceive('purchase_units')
            ->once()
            ->andReturn(
                [
                    \Mockery::mock(PurchaseUnit::class, ['shipping' => null]),
                    $purchaseUnit,
                    $purchaseUnitLast,
                ]
            );

        $this->buildTestee()[0]->shouldReceive('order')
            ->andReturn($order);

        $testee = $this->buildTestee()[1];
        $method = (new \ReflectionClass($testee))
            ->getMethod('read_shipping_from_order');
        $method->setAccessible(true);

        self::assertSame(
            $shipping,
            $method->invoke($testee)
        );
        self::assertSame(
            $shipping,
            $method->invoke($testee)
        );
    }

    /**
     * @return MockInterface[]
     */
    private function buildTestee(): array
    {
        if (! $this->mocks) {
            $sessionHandler = \Mockery::mock(SessionHandler::class);
            $sessionHandler->shouldReceive('checkout_form')->byDefault()->andReturn([]);
            $testee = new CheckoutPayPalAddressPreset($sessionHandler);
            $this->mocks = [
                $sessionHandler,
                $testee,
            ];
        }

        return $this->mocks;
    }
}
