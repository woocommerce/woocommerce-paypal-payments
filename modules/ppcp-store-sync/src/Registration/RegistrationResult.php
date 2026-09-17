<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

/**
 * DTO representing the result of a registration/deregistration webhook call.
 */
class RegistrationResult
{
    /**
     * Whether the webhook call was successful.
     *
     * @var bool
     */
    public bool $success;
    /**
     * Success/informational message from PayPal.
     *
     * @var string
     */
    public string $message;
    /**
     * Error message from PayPal (null if successful).
     *
     * @var string|null
     */
    public ?string $error;
    /**
     * Constructor.
     *
     * @param bool        $success Whether the webhook call was successful.
     * @param string      $message Success/informational message.
     * @param string|null $error   Error message (null if successful).
     */
    public function __construct(bool $success, string $message, ?string $error)
    {
        $this->success = $success;
        $this->message = $message;
        $this->error = $error;
    }
}
