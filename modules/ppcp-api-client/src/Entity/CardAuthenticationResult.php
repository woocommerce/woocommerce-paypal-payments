<?php

/**
 * The CardAuthenticationResult object
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class CardAuthenticationResult
 */
class CardAuthenticationResult
{
    const LIABILITY_SHIFT_POSSIBLE = 'POSSIBLE';
    const LIABILITY_SHIFT_NO = 'NO';
    const LIABILITY_SHIFT_UNKNOWN = 'UNKNOWN';
    const ENROLLMENT_STATUS_YES = 'Y';
    const ENROLLMENT_STATUS_NO = 'N';
    const ENROLLMENT_STATUS_UNAVAILABLE = 'U';
    const ENROLLMENT_STATUS_BYPASS = 'B';
    const AUTHENTICATION_RESULT_YES = 'Y';
    const AUTHENTICATION_RESULT_NO = 'N';
    const AUTHENTICATION_RESULT_REJECTED = 'R';
    const AUTHENTICATION_RESULT_ATTEMPTED = 'A';
    const AUTHENTICATION_RESULT_UNABLE = 'U';
    const AUTHENTICATION_RESULT_CHALLENGE_REQUIRED = 'C';
    const AUTHENTICATION_RESULT_INFO = 'I';
    const AUTHENTICATION_RESULT_DECOUPLED = 'D';
    /**
     * The liability shift.
     *
     * @var string
     */
    private $liability_shift;
    /**
     * The enrollment status.
     *
     * @var string
     */
    private $enrollment_status;
    /**
     * The authentication result.
     *
     * @var string
     */
    private $authentication_result;
    /**
     * CardAuthenticationResult constructor.
     *
     * @param string $liability_shift The liability shift.
     * @param string $enrollment_status The enrollment status.
     * @param string $authentication_result The authentication result.
     */
    public function __construct(string $liability_shift, string $enrollment_status, string $authentication_result)
    {
        $this->liability_shift = strtoupper($liability_shift);
        $this->enrollment_status = strtoupper($enrollment_status);
        $this->authentication_result = strtoupper($authentication_result);
    }
    /**
     * Returns the liability shift.
     *
     * @return string
     */
    public function liability_shift(): string
    {
        return $this->liability_shift;
    }
    /**
     * Returns the enrollment status.
     *
     * @return string
     */
    public function enrollment_status(): string
    {
        return $this->enrollment_status;
    }
    /**
     * Returns the authentication result.
     *
     * @return string
     */
    public function authentication_result(): string
    {
        return $this->authentication_result;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $data = array();
        $data['liability_shift'] = $this->liability_shift();
        $data['three_d_secure'] = array('enrollment_status' => $this->enrollment_status(), 'authentication_status' => $this->authentication_result());
        return $data;
    }
}
