<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Helper;

use Inpsyde\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;

class ThreeDSecure
{

    public const NO_DECISION = 0;
    public const PROCCEED = 1;
    public const REJECT = 2;
    public const RETRY = 3;

    /**
     * Determine, how we proceed with a given order.
     *
     * @link https://developer.paypal.com/docs/business/checkout/add-capabilities/3d-secure/#authenticationresult
     * @param Order $order
     * @return int
     */
    public function proceedWithOrder(Order $order): int
    {
        if (! $order->paymentSource()) {
            return self::NO_DECISION;
        }
        if (! $order->paymentSource()->card()) {
            return self::NO_DECISION;
        }
        if (! $order->paymentSource()->card()->authenticationResult()) {
            return self::NO_DECISION;
        }
        $result = $order->paymentSource()->card()->authenticationResult();
        if ($result->liabilityShift() === CardAuthenticationResult::LIABILITY_SHIFT_POSSIBLE) {
            return self::PROCCEED;
        }

        if ($result->liabilityShift() === CardAuthenticationResult::LIABILITY_SHIFT_UNKNOWN) {
            return self::RETRY;
        }
        if ($result->liabilityShift() === CardAuthenticationResult::LIABILITY_SHIFT_NO) {
            return $this->noLiabilityShift($result);
        }
        return self::NO_DECISION;
    }

    /**
     * @return int
     */
    private function noLiabilityShift(CardAuthenticationResult $result): int
    {

        if (
            $result->enrollmentStatus() === CardAuthenticationResult::ENROLLMENT_STATUS_BYPASS
            && ! $result->authenticationResult()
        ) {
            return self::PROCCEED;
        }
        if (
            $result->enrollmentStatus() === CardAuthenticationResult::ENROLLMENT_STATUS_UNAVAILABLE
            && ! $result->authenticationResult()
        ) {
            return self::PROCCEED;
        }
        if (
            $result->enrollmentStatus() === CardAuthenticationResult::ENROLLMENT_STATUS_NO
            && ! $result->authenticationResult()
        ) {
            return self::PROCCEED;
        }

        if ($result->authenticationResult() === CardAuthenticationResult::AUTHENTICATION_RESULT_REJECTED) {
            return self::REJECT;
        }

        if ($result->authenticationResult() === CardAuthenticationResult::AUTHENTICATION_RESULT_NO) {
            return self::REJECT;
        }

        if ($result->authenticationResult() === CardAuthenticationResult::AUTHENTICATION_RESULT_UNABLE) {
            return self::RETRY;
        }

        if (! $result->authenticationResult()) {
            return self::RETRY;
        }
        return self::NO_DECISION;
    }
}
