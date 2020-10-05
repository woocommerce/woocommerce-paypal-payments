<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\TestCase;

class PaymentsTest extends TestCase
{
	public function testAuthorizations()
	{
		$authorization = \Mockery::mock(Authorization::class);
		$authorizations = [$authorization];

		$testee = new Payments($authorizations, []);

		$this->assertEquals($authorizations, $testee->authorizations());
	}
	public function testCaptures()
	{
		$capture = \Mockery::mock(Capture::class);
		$captures = [$capture];

		$testee = new Payments([], $captures);

		$this->assertEquals($captures, $testee->captures());
	}

    public function testToArray()
    {
        $authorization = \Mockery::mock(Authorization::class);
        $authorization->shouldReceive('to_array')->andReturn(
            [
                'id' => 'foo',
                'status' => 'CREATED',
            ]
        );
	    $capture = \Mockery::mock(Capture::class);
	    $capture->shouldReceive('to_array')->andReturn(
		    [
			    'id' => 'capture',
			    'status' => 'CREATED',
		    ]
	    );
	    $captures = [$capture];
        $authorizations = [$authorization];

        $testee = new Payments($authorizations, $captures);

        $this->assertEquals(
            [
                'authorizations' => [
                    [
                        'id' => 'foo',
                        'status' => 'CREATED',
                    ],
                ],
	            'captures' => [
		            [
			            'id' => 'capture',
			            'status' => 'CREATED',
		            ],
	            ],
            ],
            $testee->to_array()
        );
    }
}
