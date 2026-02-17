<?php

/**
 * Defines a geographical coordinate.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;
/**
 * @see GeoCoordinatesTest - Unit tests for this class.
 */
class GeoCoordinates extends \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\AgenticSchema
{
    private ?float $latitude = null;
    private ?float $longitude = null;
    private ?string $subdivision = null;
    private ?string $country_code = null;
    protected function parse_fields(array $input, callable $add_issue): void
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
                    $add_issue(new InvalidData('Invalid latitude', 'Latitude must be a decimal value between -90.0 and 90.0', 'latitude'));
                } else {
                    $this->latitude = $latitude;
                }
            } else {
                $add_issue(new InvalidData('Invalid latitude', 'Latitude must be a decimal value between -90.0 and 90.0', 'latitude'));
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
                    $add_issue(new InvalidData('Invalid longitude', 'Longitude must be a decimal value between -180.0 and 180.0', 'longitude'));
                } else {
                    $this->longitude = $longitude;
                }
            } else {
                $add_issue(new InvalidData('Invalid longitude', 'Longitude must be a decimal value between -180.0 and 180.0', 'longitude'));
            }
        }
        if (isset($input['subdivision']) && is_string($input['subdivision'])) {
            $subdivision = strtoupper(trim($input['subdivision']));
            if (strlen($subdivision) > 10) {
                $add_issue(new InvalidData('Subdivision too long', 'The subdivision code must be in ISO 3166-2 format (no country code).', 'subdivision'));
            } elseif (!preg_match('/^[A-Z0-9-]+$/', $subdivision)) {
                $add_issue(new InvalidData('Subdivision invalid', 'The subdivision code must be in ISO 3166-2 format.', 'subdivision'));
            } else {
                $this->subdivision = $subdivision;
            }
        }
        if (isset($input['country_code']) && is_string($input['country_code'])) {
            $country_code = strtoupper(trim($input['country_code']));
            if (!preg_match('/^[A-Z]{2}$/', $country_code)) {
                $add_issue(new InvalidData('Country code invalid', 'The country code must be a 2-letter value.', 'country_code'));
            } else {
                $this->country_code = $country_code;
            }
        }
    }
    public function latitude(): ?float
    {
        return $this->latitude;
    }
    public function longitude(): ?float
    {
        return $this->longitude;
    }
    public function subdivision(): ?string
    {
        return $this->subdivision;
    }
    public function country_code(): ?string
    {
        return $this->country_code;
    }
}
