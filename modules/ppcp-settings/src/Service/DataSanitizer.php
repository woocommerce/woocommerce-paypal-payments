<?php

/**
 * Provides data sanitization logic.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
/**
 * DataSanitizer service. Generally used by REST endpoints (sanitize input data)
 * and data models (sanitize data during DB access)
 */
class DataSanitizer
{
    /**
     * Sanitizes the provided styling data.
     *
     * @param mixed   $data     The styling data to sanitize.
     * @param ?string $location Name of the location.
     * @return LocationStylingDTO Styling data.
     */
    public function sanitize_location_style($data, ?string $location = null): LocationStylingDTO
    {
        if ($data instanceof LocationStylingDTO) {
            if ($location) {
                $data->location = $location;
            }
            return $data;
        }
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (!is_array($data)) {
            return new LocationStylingDTO($location ?? '');
        }
        if (null === $location) {
            $location = $data['location'] ?? '';
        }
        $is_enabled = $this->sanitize_bool($data['enabled'] ?? \true);
        $shape = $this->sanitize_text($data['shape'] ?? 'rect');
        $label = $this->sanitize_text($data['label'] ?? 'pay');
        $color = $this->sanitize_text($data['color'] ?? 'gold');
        $layout = $this->sanitize_text($data['layout'] ?? 'vertical');
        $tagline = $this->sanitize_bool($data['tagline'] ?? \false);
        $methods = $this->sanitize_array($data['methods'] ?? array(), array($this, 'sanitize_text'));
        return new LocationStylingDTO($location, $is_enabled, $methods, $shape, $label, $color, $layout, $tagline);
    }
    /**
     * Helper. Ensures the value is a string.
     *
     * @param mixed  $value   Value to sanitize.
     * @param string $default Default value.
     * @return string Sanitized string.
     */
    public function sanitize_text($value, string $default = ''): string
    {
        return sanitize_text_field($value ?? $default);
    }
    /**
     * Helper. Ensures the matches one of the provided enumerations.
     *
     * The comparison is case-insensitive, if no valid default is given, the
     * first $valid_values entry is returned on failure.
     *
     * @param mixed    $value        Value to sanitize.
     * @param string[] $valid_values List of allowed return values. Must use ASCII-only characters.
     * @param string   $default      Default value.
     * @return string Sanitized string.
     */
    public function sanitize_enum($value, array $valid_values, string $default = ''): string
    {
        if (empty($valid_values)) {
            return $default;
        }
        $value = $this->sanitize_text($value);
        $match = $this->find_enum_value($value, $valid_values);
        if ($match) {
            return $match;
        }
        $default_match = $this->find_enum_value($default, $valid_values);
        if ($default_match) {
            return $default_match;
        }
        return $valid_values[0];
    }
    /**
     * Helper. Ensures the value is a boolean.
     *
     * @param mixed $value Value to sanitize.
     * @return bool Sanitized boolean.
     */
    public function sanitize_bool($value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }
    /**
     * Helper. Ensures the value is an integer.
     *
     * Attention: When passing a non-integer value (like 12.5 or "12a") the
     * function will return 0.
     *
     * @param mixed $value Value to sanitize.
     * @return int Sanitized integer.
     */
    public function sanitize_int($value): int
    {
        return (int) filter_var($value, \FILTER_VALIDATE_INT);
    }
    /**
     * Helper. Ensures the value is an array and all items are sanitized.
     *
     * @param null|array $array             Value to sanitize.
     * @param callable   $sanitize_callback Callback to sanitize each item in the array.
     * @return array Array with sanitized items.
     */
    public function sanitize_array(?array $array, callable $sanitize_callback): array
    {
        if (!is_array($array)) {
            return array();
        }
        return array_map($sanitize_callback, $array);
    }
    /**
     * Helper function to find a case-insensitive match in the valid values array.
     *
     * @param string   $value        Value to find.
     * @param string[] $valid_values List of allowed values.
     * @return string|null Matching value if found, null otherwise.
     */
    private function find_enum_value(string $value, array $valid_values): ?string
    {
        foreach ($valid_values as $valid_value) {
            // Compare both strings case-insensitive and binary safe.
            // Note: This function is safe for ASCII but can fail for unicode-characters.
            if (0 === strcasecmp($value, $valid_value)) {
                return $valid_value;
            }
        }
        return null;
    }
}
