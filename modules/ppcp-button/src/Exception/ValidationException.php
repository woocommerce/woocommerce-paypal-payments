<?php

/**
 * ValidationException.
 *
 * @package WooCommerce\PayPalCommerce\Button\Exception
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Exception;

/**
 * Class ValidationException
 */
class ValidationException extends \WooCommerce\PayPalCommerce\Button\Exception\RuntimeException
{
    /**
     * The error messages.
     *
     * @var string[]
     */
    protected $errors;
    /**
     * ValidationException constructor.
     *
     * @param string[] $errors The validation error messages.
     * @param string   $message The error message.
     */
    public function __construct(array $errors, string $message = '')
    {
        $this->errors = $errors;
        if (!$message) {
            $message = implode(' ', $errors);
        }
        parent::__construct($message);
    }
    /**
     * The error messages.
     *
     * @return string[]
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
