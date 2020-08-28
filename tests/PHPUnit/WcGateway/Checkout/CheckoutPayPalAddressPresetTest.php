<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Checkout;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Address;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PayerName;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Phone;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PhoneWithType;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Shipping;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\TestCase;
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
     * @see testFilterCheckoutField
     */
    public function filterCheckoutFieldData(): array
    {
        $order = \Mockery::mock(
            Order::class,
            [
                'id' => 'abc123def',
                'purchaseUnits' => [
                    \Mockery::mock(
                        PurchaseUnit::class,
                        [
                            'shipping' => \Mockery::mock(
                                Shipping::class,
                                [
                                    'address' => \Mockery::mock(
                                        Address::class,
                                        [
                                            'addressLine1' => 'Unter den Linden 1',
                                            'addressLine2' => '2. Stock Hinterhaus',
                                            'postalCode' => '10117',
                                            'countryCode' => 'DE',
                                            'adminArea1' => 'BE',
                                            'adminArea2' => 'Berlin',
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
                                'givenName' => 'John',
                                'surname' => 'Doe',
                            ]
                        ),
                        'emailAddress' => 'mail@domain.tld',
                        'phone' => \Mockery::mock(
                            PhoneWithType::class,
                            [
                                'phone' => \Mockery::mock(
                                    Phone::class,
                                    [
                                        'nationalNumber' => '+4912345678',
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
        $order->shouldReceive('purchaseUnits')
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
            ->getMethod('readShippingFromOrder');
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
            $testee = new CheckoutPayPalAddressPreset($sessionHandler);
            $this->mocks = [
                $sessionHandler,
                $testee,
            ];
        }

        return $this->mocks;
    }
}
