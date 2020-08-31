<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class CardAuthenticationResult
{

    public const LIABILITY_SHIFT_POSSIBLE = 'POSSIBLE';
    public const LIABILITY_SHIFT_NO = 'NO';
    public const LIABILITY_SHIFT_UNKNOWN = 'UNKNOWN';

    public const ENROLLMENT_STATUS_YES = 'Y';
    public const ENROLLMENT_STATUS_NO = 'N';
    public const ENROLLMENT_STATUS_UNAVAILABLE = 'U';
    public const ENROLLMENT_STATUS_BYPASS = 'B';

    public const AUTHENTICATION_RESULT_YES = 'Y';
    public const AUTHENTICATION_RESULT_NO = 'N';
    public const AUTHENTICATION_RESULT_REJECTED = 'R';
    public const AUTHENTICATION_RESULT_ATTEMPTED = 'A';
    public const AUTHENTICATION_RESULT_UNABLE = 'U';
    public const AUTHENTICATION_RESULT_CHALLENGE_REQUIRED = 'C';
    public const AUTHENTICATION_RESULT_INFO = 'I';
    public const AUTHENTICATION_RESULT_DECOUPLED = 'D';

    private $liabilityShift;
    private $enrollmentStatus;
    private $authenticationResult;

    public function __construct(
        string $liabilityShift,
        string $enrollmentStatus,
        string $authenticationResult
    ) {

        $this->liabilityShift = strtoupper($liabilityShift);
        $this->enrollmentStatus = strtoupper($enrollmentStatus);
        $this->authenticationResult = strtoupper($authenticationResult);
    }

    public function liabilityShift(): string
    {

        return $this->liabilityShift;
    }

    public function enrollmentStatus(): string
    {

        return $this->enrollmentStatus;
    }

    public function authenticationResult(): string
    {

        return $this->authenticationResult;
    }

    public function toArray(): array
    {
        $data = [];
        $data['liability_shift'] = $this->liabilityShift();
        $data['three_d_secure'] = [
            'enrollment_status' => $this->enrollmentStatus(),
            'authentication_result' => $this->authenticationResult(),
        ];
        return $data;
    }
}
