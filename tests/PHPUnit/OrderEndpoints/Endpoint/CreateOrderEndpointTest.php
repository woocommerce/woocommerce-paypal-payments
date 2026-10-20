<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;


use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ReturnUrlFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ContactPreferenceFactory;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\CardBillingMode;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;

use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CreateOrderEndpointTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /**
     * @dataProvider dataForTestPhoneNumber
     * @test
     *
     * @param $data
     * @param $expectedResult
     */
    public function payerVerifiesPhoneNumber($data, $expectedResult)
    {
        list($payer_factory, $testee) = $this->mockTestee();

        $method = $this->makePrivateMethod(CreateOrderEndpoint::class, 'payer');
        $dataString = wp_json_encode($expectedResult['payer']);
        $dataObj = json_decode(wp_json_encode($expectedResult['payer']));

        expect('wp_json_encode')->once()->with($expectedResult['payer'])
            ->andReturn($dataString);
        expect('json_decode')->once()->with($dataString)->andReturn($dataObj);


        $payer_factory->expects('from_paypal_response')->with($dataObj);

        $method->invokeArgs($testee, array($data));
    }

    public function dataForTestPhoneNumber() : array {

        return [
            'emptyStringPhone' => [
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName'],
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>''
                            ]
                        ]
                    ]
                ],
                [
                    'context' => 'none',
                    'payer' => [
                        'name' => ['given_name' => 'testName']
                    ]
                ]
            ],
            'tooLongStringPhone' => [
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName'],
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>'43241341234123412341234123123412341'
                            ]
                        ]
                    ]
                ],
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName'],
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>'43241341234123'
                            ]
                        ]
                    ]
                ]
            ],
            'removeNonISOStringPhone' => [
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName'],
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>'432a34as73737373'
                            ]
                        ]
                    ]
                ],
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName'],
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>'4323473737373'
                            ]
                        ]
                    ]
                ]
            ],
            'notNumbersStringPhone' => [
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName'],
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>'this is_notaPhone'
                            ]
                        ]
                    ]
                ],
                [
                    'context' => 'none',
                    'payer'=>[
                        'name'=>['given_name'=>'testName']
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    protected function mockTestee()
    {
        $request_data = Mockery::mock(RequestData::class);
		$shippingPreferenceFactory = Mockery::mock(ShippingPreferenceFactory::class);
		$returnUrlFactory = Mockery::mock(ReturnUrlFactory::class);
		$contactPreferenceFactory = Mockery::mock(ContactPreferenceFactory::class);
		$experienceContextBuilder = Mockery::mock(ExperienceContextBuilder::class);
        $purchase_unit_factory = Mockery::mock(PurchaseUnitFactory::class);
        $order_endpoint = Mockery::mock(OrderEndpoint::class);
        $payer_factory = Mockery::mock(PayerFactory::class);
        $session_handler = Mockery::mock(SessionHandler::class);
        $settings_provider = Mockery::mock(SettingsProvider::class);
	    $settings_provider->shouldReceive('save_paypal_and_venmo')->andReturn(false);
        $early_order_handler = Mockery::mock(EarlyOrderHandler::class);

        $testee = new CreateOrderEndpoint(
            $request_data,
            $purchase_unit_factory,
			$shippingPreferenceFactory,
			$returnUrlFactory,
			$contactPreferenceFactory,
			$experienceContextBuilder,
            $order_endpoint,
            $payer_factory,
            $session_handler,
            $settings_provider,
            $early_order_handler,
			Mockery::mock(CartDataFactory::class),
			Mockery::mock(CartDataTransientStorage::class),
			false,
			CardBillingMode::MINIMAL_INPUT,
			false,
			['checkout'],
			false,
			false,
			['paypal'],
			new NullLogger()
        );
        return array($payer_factory, $testee);
    }

    /**
     * GIVEN a cart that is not a free trial and whose order total is zero
     * WHEN the create-order request is handled
     * THEN the shopper is told the order total has changed
     * AND no PayPal order is requested
     */
    public function test_zero_total_cart_is_refused_with_a_reload_message_instead_of_calling_paypal(): void
    {
        $purchase_unit = $this->create_purchase_unit_with_total(0.0);
        $shipping_preference_factory = Mockery::mock(ShippingPreferenceFactory::class);

        $testee = $this->create_cart_testee(
            false,
            ['context' => 'checkout', 'payment_method' => 'ppcp-gateway', 'funding_source' => 'paypal'],
            $purchase_unit,
            $shipping_preference_factory
        );

        $error_payload = null;
        when('WC')->justReturn((object) ['cart' => null]);
        expect('wp_send_json_error')->once()->andReturnUsing(
            static function ($payload) use (&$error_payload): void {
                $error_payload = $payload;
            }
        );

        $testee->handle_request();

        $this->assertSame(
            'The order total has changed. Please reload the page and try again.',
            $error_payload['message']
        );
    }

    /**
     * GIVEN a free-trial cart with a zero total, paid for by card
     * WHEN the create-order request is handled
     * THEN the order amount is raised to 1.00 so PayPal accepts it
     * AND the shopper is not told the order total has changed
     *
     * @dataProvider card_payment_provider
     */
    public function test_free_trial_card_payment_is_sent_to_paypal_with_a_one_unit_amount(
        string $payment_method,
        string $funding_source
    ): void {
        $purchase_unit = $this->create_purchase_unit_with_total(0.0);

        $shipping_preference_factory = Mockery::mock(ShippingPreferenceFactory::class);
        $shipping_preference_factory->shouldReceive('from_state')
            ->andThrow(new \RuntimeException('reached-paypal-request'));

        $testee = $this->create_cart_testee(
            true,
            ['context' => 'checkout', 'payment_method' => $payment_method, 'funding_source' => $funding_source],
            $purchase_unit,
            $shipping_preference_factory
        );

        $error_payload = null;
        when('WC')->justReturn((object) ['cart' => null]);
        expect('wp_send_json_error')->once()->andReturnUsing(
            static function ($payload) use (&$error_payload): void {
                $error_payload = $payload;
            }
        );

        $testee->handle_request();

        $this->assertSame(1.0, $purchase_unit->amount()->value());
        $this->assertSame('reached-paypal-request', $error_payload['message']);
    }

    public function card_payment_provider(): array
    {
        return [
            'credit card gateway'            => [CreditCardGateway::ID, ''],
            'card button gateway'            => [CardButtonGateway::ID, ''],
            'PayPal gateway funded by card'  => [PayPalGateway::ID, 'card'],
        ];
    }

    /**
     * A PurchaseUnit stand-in whose amount can be replaced, as the endpoint does for free trials.
     */
    private function create_purchase_unit_with_total(float $total)
    {
        $amount = new Amount(new Money($total, 'USD'));

        $purchase_unit = Mockery::mock(PurchaseUnit::class);
        $purchase_unit->shouldReceive('amount')->andReturnUsing(
            static function () use (&$amount): Amount {
                return $amount;
            }
        );
        $purchase_unit->shouldReceive('set_amount')->andReturnUsing(
            static function (Amount $new_amount) use (&$amount): void {
                $amount = $new_amount;
            }
        );
        $purchase_unit->shouldReceive('set_custom_id')->byDefault();

        return $purchase_unit;
    }

    private function create_cart_testee(
        bool $free_trial_cart,
        array $request,
        $purchase_unit,
        $shipping_preference_factory
    ): CreateOrderEndpoint {
        $request_data = Mockery::mock(RequestData::class);
        $request_data->shouldReceive('read_request')->andReturn($request);

        $purchase_unit_factory = Mockery::mock(PurchaseUnitFactory::class);
        $purchase_unit_factory->shouldReceive('from_wc_cart')->andReturn($purchase_unit);

        $settings_provider = Mockery::mock(SettingsProvider::class);
        $settings_provider->shouldReceive('save_paypal_and_venmo')->andReturn(false);

        $args = [
            $request_data,
            $purchase_unit_factory,
            $shipping_preference_factory,
            Mockery::mock(ReturnUrlFactory::class),
            Mockery::mock(ContactPreferenceFactory::class),
            Mockery::mock(ExperienceContextBuilder::class),
            Mockery::mock(OrderEndpoint::class),
            Mockery::mock(PayerFactory::class),
            Mockery::mock(SessionHandler::class),
            $settings_provider,
            Mockery::mock(EarlyOrderHandler::class),
            Mockery::mock(CartDataFactory::class),
            Mockery::mock(CartDataTransientStorage::class),
            false,
            CardBillingMode::MINIMAL_INPUT,
            false,
            ['checkout'],
            false,
            false,
            ['paypal'],
            new NullLogger(),
        ];

        if (! $free_trial_cart) {
            return new CreateOrderEndpoint(...$args);
        }

        // Declared inline so CreateOrderEndpoint is only autoloaded once a test runs.
        return new class(...$args) extends CreateOrderEndpoint {
            protected function is_free_trial_cart(): bool
            {
                return true;
            }
        };
    }

    /**
     * @param $class
     *
     * @param $method
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    protected function makePrivateMethod($class, $method)
    {
        $reflector = new ReflectionClass($class);
        $method = $reflector->getMethod($method);
        $method->setAccessible(true);
        return $method;
    }
}