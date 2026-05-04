<?php

/**
 * PayPal Settings Blueprint Importer.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Automattic\WooCommerce\Blueprint\StepProcessor;
use Automattic\WooCommerce\Blueprint\StepProcessorResult;
use Automattic\WooCommerce\Blueprint\Steps\SetSiteOptions;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\PayLaterMessagingDTO;
/**
 * PayPal Settings Importer.
 */
class PayPalSettingsImporter implements StepProcessor
{
    /**
     * Sentinel value to detect if option doesn't exist.
     */
    private const OPTION_NOT_FOUND = '__PAYPAL_OPTION_NOT_FOUND__';
    /**
     * Process PayPal settings import.
     *
     * @param object $schema Schema object.
     * @return StepProcessorResult
     */
    public function process($schema): StepProcessorResult
    {
        $result = StepProcessorResult::success(SetSiteOptions::get_step_name());
        if (!isset($schema->options) || !is_object($schema->options)) {
            $result->add_error('Invalid PayPal options data');
            return $result;
        }
        // Validate that the object can be meaningfully converted to array.
        if (!$this->is_valid_options_object($schema->options)) {
            $result->add_error('PayPal options data is not in the expected format');
            return $result;
        }
        $options = (array) $schema->options;
        $imported_count = 0;
        foreach ($options as $option_name => $option_value) {
            // Validate option name first (before using it in any operations).
            if (!$this->is_valid_option_name($option_name)) {
                $result->add_error('Invalid option name provided');
                continue;
            }
            // Validate option value early.
            if (!$this->is_valid_option_value($option_value)) {
                $sanitized_name = sanitize_text_field((string) $option_name);
                $result->add_warn("Skipped option with invalid value: {$sanitized_name}");
                continue;
            }
            // Check if this is a PayPal-related option.
            if (!$this->is_paypal_option($option_name)) {
                $sanitized_name = sanitize_text_field($option_name);
                $result->add_warn("Skipped non-PayPal option: {$sanitized_name}");
                continue;
            }
            // Attempt to update the option with proper error handling.
            if ($this->update_option_safely($option_name, $option_value)) {
                ++$imported_count;
            } else {
                $sanitized_name = sanitize_text_field($option_name);
                $result->add_error("Failed to update option: {$sanitized_name}");
            }
        }
        $result->add_info("Successfully imported {$imported_count} PayPal options");
        return $result;
    }
    /**
     * Get step class.
     *
     * @return string
     */
    public function get_step_class(): string
    {
        return SetSiteOptions::class;
    }
    /**
     * Check capabilities.
     *
     * @param object $schema Schema object.
     * @return bool
     */
    public function check_step_capabilities($schema): bool
    {
        return current_user_can('manage_woocommerce') && current_user_can('manage_options');
    }
    /**
     * Validate that the options object can be meaningfully converted to array.
     *
     * @param object $options The options object.
     * @return bool
     */
    private function is_valid_options_object(object $options): bool
    {
        // Check if it's a stdClass or iterable object that can be cast to array.
        return $options instanceof \stdClass || is_iterable($options);
    }
    /**
     * Validate option name.
     *
     * @param mixed $option_name The option name to validate.
     * @return bool
     */
    private function is_valid_option_name($option_name): bool
    {
        return is_string($option_name) && !empty(trim($option_name)) && strlen($option_name) <= 191;
    }
    /**
     * Validate option value for WordPress options.
     *
     * @param mixed $option_value The option value to validate.
     * @return bool
     */
    private function is_valid_option_value($option_value): bool
    {
        // WordPress options should be scalar, array, or object (but not resources or closures).
        if (is_resource($option_value) || $option_value instanceof \Closure) {
            return \false;
        }
        if (null === $option_value) {
            return \false;
        }
        return \true;
    }
    /**
     * Check if option is in the PayPal options allowlist.
     *
     * @param string $option_name Option name.
     * @return bool
     */
    private function is_paypal_option(string $option_name): bool
    {
        return in_array($option_name, \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalBlueprintOptions::OPTION_NAMES, \true);
    }
    /**
     * Safely update an option with proper comparison for existing values.
     *
     * @param string $option_name  Option name.
     * @param mixed  $option_value Option value.
     * @return bool
     */
    private function update_option_safely(string $option_name, $option_value): bool
    {
        // Convert stdClass objects from JSON decode to arrays.
        $option_value = $this->convert_objects_to_arrays($option_value);
        // Hydrate DTO-based options so typed objects are preserved in the database.
        $option_value = $this->hydrate_dtos($option_name, $option_value);
        // Get the current value with a sentinel to distinguish between false and non-existent.
        $current_value = get_option($option_name, self::OPTION_NOT_FOUND);
        // If the values are already equal, consider it a success.
        if (self::OPTION_NOT_FOUND !== $current_value && $this->values_are_equal($current_value, $option_value)) {
            return \true;
        }
        return update_option($option_name, $option_value);
    }
    /**
     * Hydrate DTO-based options so the data models can load them correctly.
     *
     * Some options store typed DTO objects (e.g. LocationStylingDTO). Blueprint
     * export serializes these to JSON objects, and on import they arrive as plain
     * arrays after convert_objects_to_arrays(). This method restores the proper
     * DTO instances before the value is written to the database.
     *
     * @param string $option_name  Option name.
     * @param mixed  $option_value Option value.
     * @return mixed
     */
    private function hydrate_dtos(string $option_name, $option_value)
    {
        if ('woocommerce-ppcp-data-styling' === $option_name && is_array($option_value)) {
            $location_keys = array('cart', 'classic_checkout', 'express_checkout', 'mini_cart', 'product');
            foreach ($location_keys as $key) {
                if (isset($option_value[$key]) && is_array($option_value[$key])) {
                    $option_value[$key] = $this->array_to_location_styling_dto($option_value[$key], $key);
                }
            }
        }
        if ('woocommerce-ppcp-data-paylater-messaging' === $option_name && is_array($option_value)) {
            $location_keys = array('cart', 'checkout', 'product', 'shop', 'home', 'custom_placement');
            foreach ($location_keys as $key) {
                if (isset($option_value[$key]) && is_array($option_value[$key])) {
                    $option_value[$key] = $this->array_to_pay_later_messaging_dto($option_value[$key], $key);
                }
            }
        }
        return $option_value;
    }
    /**
     * Convert an array to a LocationStylingDTO instance.
     *
     * @param array  $data     The array data.
     * @param string $location The location key.
     * @return LocationStylingDTO
     */
    private function array_to_location_styling_dto(array $data, string $location): LocationStylingDTO
    {
        return new LocationStylingDTO($data['location'] ?? $location, (bool) ($data['enabled'] ?? \true), (array) ($data['methods'] ?? array()), (string) ($data['shape'] ?? 'rect'), (string) ($data['label'] ?? 'pay'), (string) ($data['color'] ?? 'gold'), (string) ($data['layout'] ?? 'vertical'), (bool) ($data['tagline'] ?? \false));
    }
    /**
     * Convert an array to a PayLaterMessagingDTO instance.
     *
     * @param array  $data     The array data.
     * @param string $location The location key.
     * @return PayLaterMessagingDTO
     */
    private function array_to_pay_later_messaging_dto(array $data, string $location): PayLaterMessagingDTO
    {
        return new PayLaterMessagingDTO($data['location'] ?? $location, (bool) ($data['enabled'] ?? \false), (string) ($data['layout'] ?? 'text'), (string) ($data['logo_type'] ?? 'inline'), (string) ($data['logo_position'] ?? 'left'), (string) ($data['text_color'] ?? 'black'), (string) ($data['text_size'] ?? '12'), (string) ($data['flex_color'] ?? 'black'), (string) ($data['flex_ratio'] ?? '8x1'));
    }
    /**
     * Recursively convert objects to arrays.
     * Blueprint data comes in as stdClass objects from JSON decode.
     *
     * @param mixed $data The data to convert.
     * @return mixed
     */
    private function convert_objects_to_arrays($data)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (is_array($data)) {
            return array_map(array($this, 'convert_objects_to_arrays'), $data);
        }
        return $data;
    }
    /**
     * Compare two values for equality, handling complex data types properly.
     *
     * @param mixed $value1 First value.
     * @param mixed $value2 Second value.
     * @return bool
     */
    private function values_are_equal($value1, $value2): bool
    {
        // For arrays and objects, serialize for comparison to handle deep equality.
        if ((is_array($value1) || is_object($value1)) && (is_array($value2) || is_object($value2))) {
            return serialize($value1) === serialize($value2);
        }
        // For scalar values, use strict comparison.
        return $value1 === $value2;
    }
}
