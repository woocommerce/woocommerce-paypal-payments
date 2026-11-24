<?php

/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WC_REST_Controller;
use WP_REST_Response;
/**
 * Base class for REST controllers in the settings module.
 */
abstract class RestEndpoint extends WC_REST_Controller
{
    /**
     * Endpoint namespace.
     */
    protected const NAMESPACE = 'wc/v3/wc_paypal';
    /**
     * Verify access.
     *
     * Override this method if custom permissions required.
     */
    public function check_permission(): bool
    {
        return current_user_can('manage_woocommerce');
    }
    /**
     * Returns a successful REST API response.
     *
     * @param mixed $data  The main response data.
     * @param array $extra Optional, additional response data.
     *
     * @return WP_REST_Response The successful response.
     */
    protected function return_success($data, array $extra = array()): WP_REST_Response
    {
        $response = array('success' => \true, 'data' => $data);
        if ($extra) {
            foreach ($extra as $key => $value) {
                if (isset($response[$key])) {
                    continue;
                }
                $response[$key] = $value;
            }
        }
        return rest_ensure_response($response);
    }
    /**
     * Returns an error REST API response.
     *
     * @param string $reason  The reason for the error.
     * @param mixed  $details Optional details about the error.
     *
     * @return WP_REST_Response The error response.
     */
    protected function return_error(string $reason, $details = null): WP_REST_Response
    {
        $response = array('success' => \false, 'message' => $reason);
        if (!is_null($details)) {
            $response['details'] = $details;
        }
        return rest_ensure_response($response);
    }
    /**
     * Sanitizes and renames input parameters, based on a field mapping.
     *
     * This method iterates through a field map, applying sanitization methods
     * to the corresponding values in the input parameters array.
     *
     * @param array $params    The input parameters to sanitize.
     * @param array $field_map An associative array mapping profile keys to sanitization rules.
     *                         Each rule should have 'js_name' and 'sanitize' keys.
     *
     * @return array An array of sanitized parameters.
     */
    protected function sanitize_for_wordpress(array $params, array $field_map): array
    {
        $sanitized = array();
        foreach ($field_map as $key => $details) {
            $source_key = $details['js_name'] ?? '';
            $sanitation_cb = $details['sanitize'] ?? null;
            // Skip missing values, skip "null" values, skip "read_only" values.
            if (!$source_key || !isset($params[$source_key]) || 'read_only' === $sanitation_cb) {
                continue;
            }
            $value = $params[$source_key];
            if (null === $sanitation_cb) {
                $sanitized[$key] = $value;
                continue;
            }
            if (is_string($sanitation_cb) && method_exists($this, $sanitation_cb)) {
                $sanitized[$key] = $this->{$sanitation_cb}($value);
            } elseif (is_callable($sanitation_cb)) {
                $sanitized[$key] = $sanitation_cb($value, $key);
            }
        }
        return $sanitized;
    }
    /**
     * Sanitizes and renames data for JavaScript, based on a field mapping.
     *
     * This method transforms the input data array according to the provided field map,
     * renaming keys to their JavaScript equivalents as specified in the mapping.
     *
     * @param array $data      The input data array to be sanitized.
     * @param array $field_map An associative array mapping PHP keys to JavaScript key names.
     *                         Each element should have a 'js_name' key specifying the JavaScript
     *                         name.
     *
     * @return array An array of sanitized data with keys renamed for JavaScript use.
     */
    protected function sanitize_for_javascript(array $data, array $field_map): array
    {
        $sanitized = array();
        foreach ($field_map as $key => $details) {
            $output_key = $details['js_name'] ?? '';
            if (!$output_key || !isset($data[$key])) {
                continue;
            }
            $sanitized[$output_key] = $data[$key];
        }
        return $sanitized;
    }
    /**
     * Sanitation callback: Convert a value to a boolean.
     *
     * @param mixed $value The value to sanitize.
     *
     * @return bool The boolean value.
     * @todo Switch to the DataSanitizer class.
     */
    public function to_boolean($value): bool
    {
        return (bool) $value;
    }
    /**
     * Sanitation callback: Convert a value to a number.
     *
     * @param mixed $value The value to sanitize.
     *
     * @return int The numeric value.
     * @todo Switch to the DataSanitizer class.
     */
    public function to_number($value): int
    {
        return (int) $value;
    }
}
