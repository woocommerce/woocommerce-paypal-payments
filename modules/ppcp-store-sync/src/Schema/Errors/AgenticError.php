<?php

/**
 * Base class for all agentic commerce REST errors.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema\Errors
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema\Errors;

use RuntimeException;
abstract class AgenticError
{
    /**
     * The error name is defined by the PayPal API, usually in upper-case, e.g. "INVALID_REQUEST".
     * Child classes must define this constant.
     */
    protected const ERROR_NAME = '';
    /**
     * The HTTP status code for the error, usually 400 or 500.
     * Child classes must define this constant.
     */
    protected const STATUS_CODE = 0;
    private string $message;
    private ?array $details;
    /**
     * Defines the error contents.
     *
     * @param string     $message Descriptive text of the error.
     * @param array|null $details Optional. Additional details about the error.
     * @throws RuntimeException When the error specs are incomplete.
     */
    public function __construct(string $message, ?array $details = null)
    {
        if (empty(static::ERROR_NAME)) {
            throw new RuntimeException('Child classes must override ERROR_NAME constant');
        }
        if (!is_numeric(static::STATUS_CODE) || static::STATUS_CODE < 400) {
            throw new RuntimeException('Child classes must define a valid STATUS_CODE constant');
        }
        if (empty($message)) {
            throw new RuntimeException('Error message cannot be empty');
        }
        $this->message = $message;
        $this->details = $details;
    }
    public function get_status_code(): int
    {
        return static::STATUS_CODE;
    }
    public function to_array(): array
    {
        $data = array('name' => static::ERROR_NAME, 'message' => $this->message);
        if ($this->details) {
            $data['details'] = $this->details;
        }
        return $data;
    }
}
