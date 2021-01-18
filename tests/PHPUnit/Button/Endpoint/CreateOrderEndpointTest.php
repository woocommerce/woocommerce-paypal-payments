<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;


use ReflectionClass;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CartRepository;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

use function Brain\Monkey\Functions\expect;

class CreateOrderEndpointTest extends TestCase
{

    /**
     * @dataProvider dataForTestPhoneNumber
     * @test
     *
     * @param $data
     * @param $expectedResult
     */
    public function payerVerifiesPhoneNumber($data, $expectedResult) {
        $request_data = Mockery::mock(RequestData::class);
		$cart_repository = Mockery::mock(CartRepository::class);
		$purchase_unit_factory = Mockery::mock(PurchaseUnitFactory::class);
		$order_endpoint = Mockery::mock(OrderEndpoint::class);
		$payer_factory = Mockery::mock(PayerFactory::class);
		$session_handler = Mockery::mock(SessionHandler::class);
		$settings = Mockery::mock(Settings::class);
		$early_order_handler = Mockery::mock(EarlyOrderHandler::class);

        $testee = new CreateOrderEndpoint(
            $request_data,
		$cart_repository,
		$purchase_unit_factory,
		$order_endpoint,
		$payer_factory,
		$session_handler,
		$settings,
		$early_order_handler
        );
        $reflector = new ReflectionClass( CreateOrderEndpoint::class );
        $method = $reflector->getMethod( 'payer' );
        $method->setAccessible( true );


        $payer = $method->invokeArgs( $testee, array( $data ) );
        $this->assertEquals($expectedResult, $payer);

    }

    public function dataForTestPhoneNumber() : array {

        return [
            'emptyStringPhone' => [
                [
                    'context' => 'none',
                    'payer'=>[
                        'phone'=>[
                            'phone_number'=>[
                                'national_number'=>'123456789876'
                            ]
                        ]
                    ]
                ],
                [
                    [
                        'context' => 'none',
                        'payer'=>[]
                    ]
                ]
            ]
        ];
    }
}