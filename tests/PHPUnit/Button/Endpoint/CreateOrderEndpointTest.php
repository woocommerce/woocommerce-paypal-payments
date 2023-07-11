<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;


use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentSourceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\CardBillingMode;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use function Brain\Monkey\Functions\expect;

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
		$shipping_preference_factory = Mockery::mock(ShippingPreferenceFactory::class);
        $purchase_unit_factory = Mockery::mock(PurchaseUnitFactory::class);
		$payment_source_factory = Mockery::mock(PaymentSourceFactory::class);
        $order_endpoint = Mockery::mock(OrderEndpoint::class);
        $payer_factory = Mockery::mock(PayerFactory::class);
        $session_handler = Mockery::mock(SessionHandler::class);
        $settings = Mockery::mock(Settings::class);
        $early_order_handler = Mockery::mock(EarlyOrderHandler::class);
		$settings->shouldReceive('has')->andReturnFalse();

        $testee = new CreateOrderEndpoint(
            $request_data,
            $purchase_unit_factory,
			$shipping_preference_factory,
			$payment_source_factory,
            $order_endpoint,
            $payer_factory,
            $session_handler,
            $settings,
            $early_order_handler,
			false,
			CardBillingMode::MINIMAL_INPUT,
			false,
			['checkout'],
			false,
			['paypal'],
			new NullLogger()
        );
        return array($payer_factory, $testee);
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
