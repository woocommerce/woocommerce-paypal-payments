<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextShippingIssue;
/**
 * Context class for shipping-related validation issues.
 *
 * All props are optional and included in to_array() only when set.
 */
class ShippingErrorContext extends \WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext
{
    public static function create_missing_shipping_address(): self
    {
        return new self(ContextShippingIssue::MISSING_SHIPPING_ADDRESS);
    }
    public static function create_shipping_address_invalid(): self
    {
        return new self(ContextShippingIssue::SHIPPING_ADDRESS_INVALID);
    }
    public static function create_shipping_to_po_box_not_allowed(): self
    {
        return new self(ContextShippingIssue::SHIPPING_TO_PO_BOX_NOT_ALLOWED);
    }
    public static function create_no_shipping_options(): self
    {
        return new self(ContextShippingIssue::NO_SHIPPING_OPTIONS);
    }
    public static function create_international_shipping_restricted(): self
    {
        return new self(ContextShippingIssue::INTERNATIONAL_SHIPPING_RESTRICTED);
    }
    public static function create_region_restricted(): self
    {
        return new self(ContextShippingIssue::REGION_RESTRICTED);
    }
    public static function create_oversized_item_shipping(): self
    {
        return new self(ContextShippingIssue::OVERSIZED_ITEM_SHIPPING);
    }
    public static function create_hazardous_material_shipping(): self
    {
        return new self(ContextShippingIssue::HAZARDOUS_MATERIAL_SHIPPING);
    }
    public static function create_shipping_zone_not_covered(): self
    {
        return new self(ContextShippingIssue::SHIPPING_ZONE_NOT_COVERED);
    }
    public static function create_missing_coordinates_for_enhanced_delivery(): self
    {
        return new self(ContextShippingIssue::MISSING_COORDINATES_FOR_ENHANCED_DELIVERY);
    }
    public static function create_shipping_not_available(): self
    {
        return new self(ContextShippingIssue::SHIPPING_NOT_AVAILABLE);
    }
    public static function create_shipping_address_unserviceable(): self
    {
        return new self(ContextShippingIssue::SHIPPING_ADDRESS_UNSERVICEABLE);
    }
    private const VALID_RESTRICTION_REASONS = array('signature_required', 'age_verification_required', 'export_controlled', 'hazardous_material', 'oversized_item', 'po_box_restriction');
    private ?array $validation_failures = null;
    private ?array $suggested_corrections = null;
    private ?float $address_quality_score = null;
    private ?array $restricted_items = null;
    private ?string $restriction_reason = null;
    private ?bool $po_box_detected = null;
    private ?string $destination_country = null;
    private ?string $restricted_region = null;
    private ?array $supported_countries = null;
    private ?string $provided_address = null;
    /**
     * Specific address validation failures.
     */
    public function validation_failures(?array $validation_failures): self
    {
        $this->validation_failures = $this->sanitize_string_array($validation_failures);
        return $this;
    }
    /**
     * Suggested address corrections.
     */
    public function suggested_corrections(?string $postal_code, ?string $address_line_1, ?string $admin_area_2): self
    {
        $corrections = array_filter(array('postal_code' => $postal_code, 'address_line_1' => $address_line_1, 'admin_area_2' => $admin_area_2), static function ($v): bool {
            return $v !== null;
        });
        $this->suggested_corrections = $corrections !== array() ? $corrections : null;
        return $this;
    }
    /**
     * Address validation quality score (0.0–1.0).
     */
    public function address_quality_score(?float $address_quality_score): self
    {
        if ($address_quality_score !== null && $address_quality_score >= 0.0 && $address_quality_score <= 1.0) {
            $this->address_quality_score = $address_quality_score;
        }
        return $this;
    }
    /**
     * Items with shipping restrictions.
     */
    public function restricted_items(?array $restricted_items): self
    {
        $this->restricted_items = $this->sanitize_string_array($restricted_items);
        return $this;
    }
    /**
     * Reason for shipping restriction.
     */
    public function restriction_reason(?string $restriction_reason): self
    {
        if (in_array($restriction_reason, self::VALID_RESTRICTION_REASONS, \true)) {
            $this->restriction_reason = $restriction_reason;
        }
        return $this;
    }
    /**
     * Whether PO Box was detected.
     */
    public function po_box_detected(?bool $po_box_detected): self
    {
        $this->po_box_detected = $po_box_detected;
        return $this;
    }
    /**
     * Destination country code.
     */
    public function destination_country(?string $destination_country): self
    {
        $this->destination_country = $destination_country;
        return $this;
    }
    /**
     * Restricted region identifier.
     */
    public function restricted_region(?string $restricted_region): self
    {
        $this->restricted_region = $restricted_region;
        return $this;
    }
    /**
     * List of supported countries.
     */
    public function supported_countries(?array $supported_countries): self
    {
        $this->supported_countries = $this->sanitize_string_array($supported_countries);
        return $this;
    }
    /**
     * Address string that failed validation.
     */
    public function provided_address(?string $provided_address): self
    {
        $this->provided_address = $provided_address;
        return $this;
    }
    public function to_array(): array
    {
        $data = array('specific_issue' => $this->specific_issue);
        if ($this->validation_failures !== null) {
            $data['validation_failures'] = $this->validation_failures;
        }
        if ($this->suggested_corrections !== null) {
            $data['suggested_corrections'] = $this->suggested_corrections;
        }
        if ($this->address_quality_score !== null) {
            $data['address_quality_score'] = $this->address_quality_score;
        }
        if ($this->restricted_items !== null) {
            $data['restricted_items'] = $this->restricted_items;
        }
        if ($this->restriction_reason !== null) {
            $data['restriction_reason'] = $this->restriction_reason;
        }
        if ($this->po_box_detected !== null) {
            $data['po_box_detected'] = $this->po_box_detected;
        }
        if ($this->destination_country !== null) {
            $data['destination_country'] = $this->destination_country;
        }
        if ($this->restricted_region !== null) {
            $data['restricted_region'] = $this->restricted_region;
        }
        if ($this->supported_countries !== null) {
            $data['supported_countries'] = $this->supported_countries;
        }
        if ($this->provided_address !== null) {
            $data['provided_address'] = $this->provided_address;
        }
        return $data;
    }
}
