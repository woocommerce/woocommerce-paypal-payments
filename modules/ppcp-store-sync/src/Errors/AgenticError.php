<?php

/**
 * Base class for all agentic commerce REST errors.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Errors
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Errors;

use RuntimeException;
use WP_Error;
/**
 * Errors represent technical failures and invalid requests.
 * Use when the request format is incorrect, authentication fails, or system errors occur.
 */
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
    private ?string $debug_id;
    /**
     * Defines the error contents.
     *
     * @param string      $message  Descriptive text of the error.
     * @param array|null  $details  Optional. Additional details about the error.
     * @param string|null $debug_id Optional. Debug identifier for support.
     * @throws RuntimeException When the error specs are incomplete.
     */
    public function __construct(string $message, ?array $details = null, ?string $debug_id = null)
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
        $this->debug_id = $debug_id ?? $this->generate_debug_id();
    }
    public function get_status_code(): int
    {
        return static::STATUS_CODE;
    }
    /**
     * Exposes the error name, mainly for logging. Note that this is actually a code, but is
     * internally referred to as "name" in the API docs.
     */
    public function get_name(): string
    {
        return static::ERROR_NAME;
    }
    public function get_debug_id(): string
    {
        return $this->debug_id ?? '';
    }
    public function to_array(): array
    {
        $data = array('name' => static::ERROR_NAME, 'message' => $this->message);
        if ($this->debug_id) {
            $data['debug_id'] = $this->debug_id;
        }
        if ($this->details) {
            $data['details'] = $this->details;
        }
        return $data;
    }
    /**
     * Create an instance from WP_Error using late static binding.
     *
     * @param WP_Error $wp_error The WordPress error to convert.
     * @return static Instance of the called class.
     */
    public static function from_wp_error(WP_Error $wp_error): \WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError
    {
        $message = $wp_error->get_error_message();
        $details = static::extract_wp_error_details($wp_error);
        /**
         * @psalm-suppress MissingThrowsDocblock, UnsafeInstantiation
         *  Parent constructor throws only on developer errors, like missing ERROR_NAME, or an
         *  invalid STATUS_CODE. These are implementation issues that should fail fast, not
         *  runtime errors requiring handling.
         */
        return new static($message, $details);
    }
    /**
     * Extract details from WP_Error.
     *
     * @param WP_Error $wp_error The WordPress error.
     * @return array Error details.
     */
    private static function extract_wp_error_details(WP_Error $wp_error): array
    {
        $details = array('wp_error_codes' => $wp_error->get_error_codes(), 'wp_error_messages' => array(), 'wp_error_data' => array());
        foreach ($wp_error->get_error_codes() as $code) {
            $details['wp_error_messages'][$code] = $wp_error->get_error_messages($code);
            $error_data = $wp_error->get_error_data($code);
            if (!empty($error_data)) {
                $details['wp_error_data'][$code] = $error_data;
            }
        }
        return $details;
    }
    /**
     * Generate a debug ID if not provided.
     */
    protected function generate_debug_id(): string
    {
        return sprintf('ERROR-%s-%s', $this->get_status_code(), strtoupper((string) substr(md5(uniqid('', \true)), 0, 8)));
    }
}
