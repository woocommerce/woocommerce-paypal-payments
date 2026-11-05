<?php

/**
 * Defines a geographical coordinate.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see GeoCoordinatesTest - Unit tests for this class.
 */
class GeoCoordinates extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?float $latitude = null;
    private ?float $longitude = null;
    private ?string $subdivision = null;
    private ?string $country_code = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->latitude = null;
        $this->longitude = null;
        $this->subdivision = null;
        $this->country_code = null;
        // Parse optional fields.
        if (isset($input['latitude'])) {
            $latitude = $input['latitude'];
            if (is_int($latitude)) {
                $latitude = (float) $latitude;
            } elseif (is_string($latitude)) {
                $latitude = trim($latitude);
                if (is_numeric($latitude)) {
                    $latitude = (float) $latitude;
                }
            }
            if (is_float($latitude)) {
                if ($latitude < -90.0 || $latitude > 90.0) {
                    $validation->add_invalid_data('latitude', 'Invalid latitude', 'Latitude must be a decimal value between -90.0 and 90.0');
                } else {
                    $this->latitude = $latitude;
                }
            } else {
                $validation->add_invalid_data('latitude', 'Invalid latitude', 'Latitude must be a decimal value between -90.0 and 90.0');
            }
        }
        if (isset($input['longitude'])) {
            $longitude = $input['longitude'];
            if (is_int($longitude)) {
                $longitude = (float) $longitude;
            } elseif (is_string($longitude)) {
                $longitude = trim($longitude);
                if (is_numeric($longitude)) {
                    $longitude = (float) $longitude;
                }
            }
            if (is_float($longitude)) {
                if ($longitude < -180.0 || $longitude > 180.0) {
                    $validation->add_invalid_data('longitude', 'Invalid longitude', 'Longitude must be a decimal value between -180.0 and 180.0');
                } else {
                    $this->longitude = $longitude;
                }
            } else {
                $validation->add_invalid_data('longitude', 'Invalid longitude', 'Longitude must be a decimal value between -180.0 and 180.0');
            }
        }
        if (isset($input['subdivision']) && is_string($input['subdivision'])) {
            $subdivision = strtoupper(trim($input['subdivision']));
            if (strlen($subdivision) > 10) {
                $validation->add_invalid_data('subdivision', 'Subdivision too long', 'The subdivision code must be in ISO 3166-2 format (no country code).');
            } elseif (!preg_match('/^[A-Z0-9-]+$/', $subdivision)) {
                $validation->add_invalid_data('subdivision', 'Subdivision invalid', 'The subdivision code must be in ISO 3166-2 format.');
            } else {
                $this->subdivision = $subdivision;
            }
        }
        if (isset($input['country_code']) && is_string($input['country_code'])) {
            $country_code = strtoupper(trim($input['country_code']));
            if (!preg_match('/^[A-Z]{2}$/', $country_code)) {
                $validation->add_invalid_data('country_code', 'Country code invalid', 'The country code must be a 2-letter value.');
            } else {
                $this->country_code = $country_code;
            }
        }
    }
    public function latitude(?float $default = null): ?float
    {
        return $this->latitude ?? $default;
    }
    public function longitude(?float $default = null): ?float
    {
        return $this->longitude ?? $default;
    }
    public function subdivision(?string $default = null): ?string
    {
        return $this->subdivision ?? $default;
    }
    public function country_code(?string $default = null): ?string
    {
        return $this->country_code ?? $default;
    }
}
