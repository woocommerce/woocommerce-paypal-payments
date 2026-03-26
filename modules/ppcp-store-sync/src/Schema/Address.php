<?php

/**
 * Defines a postal address (shipping or billing).
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * @see AddressTest - Unit tests for this class.
 */
class Address extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $country_code = null;
    private ?string $address_line_1 = null;
    private ?string $address_line_2 = null;
    private ?string $admin_area_2 = null;
    private ?string $admin_area_1 = null;
    private ?string $postal_code = null;
    protected function parse_fields(array $input, callable $add_issue): void
    {
        // Reset all fields.
        $this->country_code = null;
        $this->address_line_1 = null;
        $this->address_line_2 = null;
        $this->admin_area_2 = null;
        $this->admin_area_1 = null;
        $this->postal_code = null;
        // Parse mandatory fields.
        if (isset($input['country_code']) && is_string($input['country_code'])) {
            $country_code = strtoupper(trim($input['country_code']));
            if (preg_match('/^[A-Z]{2}$/', $country_code)) {
                $this->country_code = $country_code;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Unexpected country_code')->user_message('Please provide a valid 2-letter country code.')->for_field('country_code'));
            }
        } else {
            $add_issue(ValidationIssue::create_invalid_data('Missing required field')->user_message('Please provide a country code.')->for_field('country_code'));
        }
        // Parse optional fields.
        if (isset($input['address_line_1']) && is_string($input['address_line_1'])) {
            $address_line_1 = trim($input['address_line_1']);
            if (strlen($address_line_1) <= 300) {
                $this->address_line_1 = $address_line_1;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Field address_line_1 is too long')->user_message('Please provide a valid address line 1.')->for_field('address_line_1'));
            }
        }
        if (isset($input['address_line_2']) && is_string($input['address_line_2'])) {
            $address_line_2 = trim($input['address_line_2']);
            if (strlen($address_line_2) <= 300) {
                $this->address_line_2 = $address_line_2;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Field address_line_2 is too long')->user_message('Please provide a valid address line 2.')->for_field('address_line_2'));
            }
        }
        if (isset($input['admin_area_2']) && is_string($input['admin_area_2'])) {
            $admin_area_2 = trim($input['admin_area_2']);
            if (strlen($admin_area_2) <= 120) {
                $this->admin_area_2 = $admin_area_2;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Field admin_area_2 is too long')->user_message('Please provide a valid city.')->for_field('admin_area_2'));
            }
        }
        if (isset($input['admin_area_1']) && is_string($input['admin_area_1'])) {
            $admin_area_1 = trim($input['admin_area_1']);
            if (strlen($admin_area_1) <= 300) {
                $this->admin_area_1 = $admin_area_1;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Field admin_area_1 is too long')->user_message('Please provide a valid region or state.')->for_field('admin_area_1'));
            }
        }
        if (isset($input['postal_code']) && is_string($input['postal_code'])) {
            $postal_code = trim($input['postal_code']);
            if (strlen($postal_code) <= 60) {
                $this->postal_code = $postal_code;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Field postal_code is too long')->user_message('Please provide a valid postal code.')->for_field('postal_code'));
            }
        }
    }
    public function country_code(): ?string
    {
        return $this->country_code;
    }
    public function address_line_1(): ?string
    {
        return $this->address_line_1;
    }
    public function address_line_2(): ?string
    {
        return $this->address_line_2;
    }
    /**
     * The city.
     */
    public function admin_area_2(): ?string
    {
        return $this->admin_area_2;
    }
    /**
     * The region or state.
     */
    public function admin_area_1(): ?string
    {
        return $this->admin_area_1;
    }
    public function postal_code(): ?string
    {
        return $this->postal_code;
    }
}
