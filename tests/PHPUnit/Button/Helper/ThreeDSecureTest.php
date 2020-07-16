<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Helper;


use Inpsyde\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentSource;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentSourceCard;
use Inpsyde\PayPalCommerce\TestCase;
use Mockery\Mock;

class ThreeDSecureTest extends TestCase
{

    /**
     * @dataProvider dataForTestDefault
     * @param Order $order
     * @param int $expected
     */
    public function testDefault(int $expected, string $liabilityShift, string $authenticationResult, string $enrollment)
    {
        $result = \Mockery::mock(CardAuthenticationResult::class);
        $result->shouldReceive('liabilityShift')->andReturn($liabilityShift);
        $result->shouldReceive('authenticationResult')->andReturn($authenticationResult);
        $result->shouldReceive('enrollmentStatus')->andReturn($enrollment);
        $card = \Mockery::mock(PaymentSourceCard::class);
        $card->shouldReceive('authenticationResult')->andReturn($result);
        $source = \Mockery::mock(PaymentSource::class);
        $source->shouldReceive('card')->andReturn($card);
        $order = \Mockery::mock(Order::class);
        $order->shouldReceive('paymentSource')->andReturn($source);
        $testee = new ThreeDSecure();
        $result = $testee->proceedWithOrder($order);
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