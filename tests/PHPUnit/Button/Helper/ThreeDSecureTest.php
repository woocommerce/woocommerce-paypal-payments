<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CardAuthenticationResultFactory;
use WooCommerce\PayPalCommerce\TestCase;

class ThreeDSecureTest extends TestCase
{

    /**
     * @dataProvider dataForTestDefault
     * @param Order $order
     * @param int $expected
     */
    public function testDefault(int $expected, string $liabilityShift, string $authenticationResult, string $enrollment)
    {
		$authResult = \Mockery::mock(CardAuthenticationResult::class);
		$authResult->shouldReceive('liability_shift')->andReturn($liabilityShift);
		$authResult->shouldReceive('authentication_result')->andReturn($authenticationResult);
		$authResult->shouldReceive('enrollment_status')->andReturn($enrollment);
		$authResult->shouldReceive('to_array')->andReturn(['foo' => 'bar',]);

		$authenticationResultFactory = \Mockery::mock(CardAuthenticationResultFactory::class);
		$authenticationResultFactory->shouldReceive('from_paypal_response')
			->andReturn($authResult);

		$source = \Mockery::mock(PaymentSource::class);
		$authentication_result = (object)[
			'brand' => 'visa',
			'authentication_result' => (object)array(
				'liability_shift' => $liabilityShift,
				'authentication_result' => $authenticationResult,
				'enrollment_status' => $enrollment
			),
		];

		$source->shouldReceive('properties')->andReturn($authentication_result);

		$order = \Mockery::mock(Order::class);
		$order->shouldReceive('payment_source')->andReturn($source);

		$logger = \Mockery::mock(LoggerInterface::class);
		$logger->shouldReceive('info');

        $testee = new ThreeDSecure($authenticationResultFactory, $logger);
        $result = $testee->proceed_with_order($order);
        $this->assertEquals($expected, $result);
    }

    public function dataForTestDefault() : array
    {
        $matrix = [
            'test_1' => [
                ThreeDSecure::PROCCEED,
                CardAuthenticationResult::LIABILITY_SHIFT_POSSIBLE,
                CardAuthenticationResult::AUTHENTICATION_RESULT_YES,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_2' => [
                ThreeDSecure::REJECT,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                CardAuthenticationResult::AUTHENTICATION_RESULT_NO,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_3' => [
                ThreeDSecure::REJECT,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                CardAuthenticationResult::AUTHENTICATION_RESULT_REJECTED,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_4' => [
                ThreeDSecure::PROCCEED,
                CardAuthenticationResult::LIABILITY_SHIFT_POSSIBLE,
                CardAuthenticationResult::AUTHENTICATION_RESULT_ATTEMPTED,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_5' => [
                ThreeDSecure::RETRY,
                CardAuthenticationResult::LIABILITY_SHIFT_UNKNOWN,
                CardAuthenticationResult::AUTHENTICATION_RESULT_UNABLE,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_6' => [
                ThreeDSecure::RETRY,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                CardAuthenticationResult::AUTHENTICATION_RESULT_UNABLE,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_7' => [
                ThreeDSecure::RETRY,
                CardAuthenticationResult::LIABILITY_SHIFT_UNKNOWN,
                CardAuthenticationResult::AUTHENTICATION_RESULT_CHALLENGE_REQUIRED,
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_8' => [
                ThreeDSecure::RETRY,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                '',
                CardAuthenticationResult::ENROLLMENT_STATUS_YES,
            ],
            'test_9' => [
                ThreeDSecure::PROCCEED,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                '',
                CardAuthenticationResult::ENROLLMENT_STATUS_NO,
            ],
            'test_10' => [
                ThreeDSecure::PROCCEED,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                '',
                CardAuthenticationResult::ENROLLMENT_STATUS_UNAVAILABLE,
            ],
            'test_11' => [
                ThreeDSecure::RETRY,
                CardAuthenticationResult::LIABILITY_SHIFT_UNKNOWN,
                '',
                CardAuthenticationResult::ENROLLMENT_STATUS_UNAVAILABLE,
            ],
            'test_12' => [
                ThreeDSecure::PROCCEED,
                CardAuthenticationResult::LIABILITY_SHIFT_NO,
                '',
                CardAuthenticationResult::ENROLLMENT_STATUS_BYPASS,
            ],
            'test_13' => [
                ThreeDSecure::RETRY,
                CardAuthenticationResult::LIABILITY_SHIFT_UNKNOWN,
                '',
                '',
            ],
        ];
        return $matrix;
    }
}
