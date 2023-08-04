<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Refund;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

class PaymentsFactoryTest extends TestCase
{
    public function testFromPayPalResponse()
    {
	    $authorization = Mockery::mock(Authorization::class);
	    $authorization->shouldReceive('to_array')->andReturn(['id' => 'foo', 'status' => 'CREATED']);
	    $capture = Mockery::mock(Capture::class);
	    $capture->shouldReceive('to_array')->andReturn(['id' => 'capture', 'status' => 'CREATED']);
		$refund = Mockery::mock(Refund::class);
		$refund->shouldReceive('to_array')->andReturn(['id' => 'refund', 'status' => 'CREATED']);

        $authorizationsFactory = Mockery::mock(AuthorizationFactory::class);
	    $authorizationsFactory->shouldReceive('from_paypal_response')->andReturn($authorization);
		$captureFactory = Mockery::mock(CaptureFactory::class);
	    $captureFactory->shouldReceive('from_paypal_response')->andReturn($capture);
		$refundFactory = Mockery::mock(RefundFactory::class);
	    $refundFactory->shouldReceive('from_paypal_response')->andReturn($refund);
        $response = (object)[
	        'authorizations' => [
		        (object)['id' => 'foo', 'status' => 'CREATED'],
	        ],
	        'captures' => [
		        (object)['id' => 'capture', 'status' => 'CREATED'],
	        ],
	        'refunds' => [
		        (object)['id' => 'refund', 'status' => 'CREATED'],
	        ],
        ];

        $testee = new PaymentsFactory($authorizationsFactory, $captureFactory, $refundFactory);
        $result = $testee->from_paypal_response($response);

        $this->assertInstanceOf(Payments::class, $result);

        $expectedToArray = [
	        'authorizations' => [
		        ['id' => 'foo', 'status' => 'CREATED'],
	        ],
	        'captures' => [
		        ['id' => 'capture', 'status' => 'CREATED'],
	        ],
			'refunds' => [
				['id' => 'refund', 'status' => 'CREATED'],
			],
        ];
        $this->assertEquals($expectedToArray, $result->to_array());
    }
}
