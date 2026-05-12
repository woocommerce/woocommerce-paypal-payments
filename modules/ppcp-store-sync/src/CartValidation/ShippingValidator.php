<?php

/**
 * Shipping Validator for Agentic Commerce.
 *
 * Validates shipping addresses and restrictions according to WooCommerce settings.
 * Covers four main scenarios:
 * 1. Missing Shipping Address (physical products, no address provided)
 * 2. Invalid Shipping Address (completeness, format)
 * 3. PO Box Restriction (signature-required items)
 * 4. Region Restricted (country not allowed)
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WC_Countries;
use WC_Validation;
use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ShippingIssue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Address;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\ShippingErrorContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
class ShippingValidator implements \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ValidatorInterface
{
    /**
     * List of shipping countries that are supported by the agentic integration.
     * Managed on plugin-side for extra code and test stability.
     */
    private const PAYPAL_SUPPORTED_COUNTRIES = array('US');
    public function validate(StorePayPalCart $store_cart)
    {
        $cart = $store_cart->paypal_cart();
        $shipping_address = $cart->shipping_address();
        // Scenario 1: Missing Shipping Address.
        if ($shipping_address->is_empty()) {
            if (!$this->cart_needs_shipping($store_cart)) {
                return null;
            }
            return array(ValidationIssue::create_missing_field('Shipping address is required')->user_message('Please provide a shipping address to continue.')->for_field('shipping_address')->add_context(ShippingErrorContext::create_missing_shipping_address())->add_resolution(ResolutionOption::create_provide_missing_field()->label('Add shipping address')->priority(Priority::HIGH)->set_meta('field', 'shipping_address')));
        }
        $issues = array();
        // Scenario 2: Invalid Shipping Address.
        $address_issues = $this->validate_address_completeness($shipping_address);
        if ($address_issues) {
            $issues = array_merge($issues, $address_issues);
        }
        // Scenario 3: PO Box Restriction.
        $po_box_issue = $this->validate_po_box_restrictions($store_cart, $shipping_address);
        if ($po_box_issue) {
            $issues[] = $po_box_issue;
        }
        // Scenario 4: Region Restricted.
        $country_issue = $this->validate_country($shipping_address->country_code());
        if ($country_issue) {
            $issues[] = $country_issue;
        }
        return $issues ?: null;
    }
    /**
     * Checks if any item in the cart requires shipping.
     *
     * @param StorePayPalCart $store_cart The cart to check.
     * @return bool True if at least one item needs shipping.
     */
    private function cart_needs_shipping(StorePayPalCart $store_cart): bool
    {
        foreach ($store_cart->cart_items() as $item) {
            if ($item->product()->needs_shipping()) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Scenario 1: Validates that the address has all required fields and proper formats.
     *
     * @param Address $address The address to validate.
     * @return ValidationIssue[] Array of validation issues.
     */
    private function validate_address_completeness(Address $address): array
    {
        $issues = array();
        if (!$address->address_line_1()) {
            $issues[] = ValidationIssue::create_invalid_address('Shipping address is missing street address')->user_message('Please provide a complete street address.')->for_field('shipping_address.address_line_1')->add_context(ShippingErrorContext::create_shipping_address_unserviceable())->add_resolution(ResolutionOption::create_provide_missing_field()->label('Provide street address')->set_meta('field', 'address_line_1'))->add_resolution(ResolutionOption::create_update_address()->label('Update shipping address')->priority(Priority::LOW));
        }
        if (!$address->admin_area_2()) {
            $issues[] = ValidationIssue::create_invalid_address('Shipping address is missing city')->user_message('Please provide a city.')->for_field('shipping_address.admin_area_2')->add_context(ShippingErrorContext::create_shipping_address_unserviceable())->add_resolution(ResolutionOption::create_provide_missing_field()->label('Provide city')->set_meta('field', 'admin_area_2'))->add_resolution(ResolutionOption::create_update_address()->label('Update shipping address')->priority(Priority::LOW));
        }
        $postal_code = $address->postal_code();
        if (!$postal_code) {
            $issues[] = ValidationIssue::create_invalid_address('Shipping address is missing postal code')->user_message('Please provide a postal code.')->for_field('shipping_address.postal_code')->add_context(ShippingErrorContext::create_shipping_address_unserviceable())->add_resolution(ResolutionOption::create_provide_missing_field()->label('Provide postal code')->set_meta('field', 'postal_code'))->add_resolution(ResolutionOption::create_update_address()->label('Update shipping address')->priority(Priority::LOW));
        } else {
            $postal_validation = $this->validate_postal_code_format($postal_code, $address->country_code());
            if ($postal_validation) {
                $issues[] = $postal_validation;
            }
        }
        return $issues;
    }
    /**
     * Validates a postal code format based on the country using WooCommerce's native validation.
     *
     * @param string      $postal_code  The postal code to validate.
     * @param string|null $country_code The country code.
     * @return ValidationIssue|null Validation issue if format is invalid.
     */
    private function validate_postal_code_format(string $postal_code, ?string $country_code): ?ValidationIssue
    {
        if (!$country_code) {
            return null;
        }
        // Use WooCommerce's native postcode validation.
        if (!class_exists(WC_Validation::class)) {
            return null;
        }
        $is_valid = WC_Validation::is_postcode($postal_code, $country_code);
        if (!$is_valid) {
            return ValidationIssue::create_invalid_address(sprintf('Invalid postal code format for %s: %s', $country_code, $postal_code))->user_message('Please provide a valid postal code.')->for_field('shipping_address.postal_code')->add_context(ShippingErrorContext::create_shipping_address_unserviceable())->add_resolution(ResolutionOption::create_update_address()->label('Correct the postal code')->priority(Priority::HIGH)->set_meta('field', 'postal_code'));
        }
        return null;
    }
    /**
     * Scenario 2: Validates PO Box restrictions for items requiring signature delivery.
     *
     * @param StorePayPalCart $store_cart The cart to validate.
     * @param Address         $address    The shipping address.
     * @return ValidationIssue|null Validation issue if PO Box restrictions apply.
     */
    private function validate_po_box_restrictions(StorePayPalCart $store_cart, Address $address): ?ValidationIssue
    {
        $address_line = $address->address_line_1();
        if (!$address_line || !$this->is_po_box($address_line)) {
            return null;
        }
        $signature_required_items = $this->find_signature_required_items($store_cart);
        if (!empty($signature_required_items)) {
            $restricted_items = array_map(static fn($item): string => $item->id(), $signature_required_items);
            return ValidationIssue::create_shipping_unavailable('PO Box delivery not available for this order')->user_message('This order contains items requiring signature confirmation and cannot be delivered to a PO Box.')->for_field('shipping_address')->add_context(ShippingErrorContext::create_shipping_to_po_box_not_allowed()->restricted_items($restricted_items)->restriction_reason('signature_required')->po_box_detected(\true))->add_resolution(ResolutionOption::create_update_address()->label('Use street address instead')->priority(Priority::HIGH))->add_resolution(ResolutionOption::create_remove_item()->label('Remove items requiring signature')->priority(Priority::LOW));
        }
        return null;
    }
    /**
     * Finds items in the cart that require signature delivery.
     *
     * @return StoreCartItem[] Array of StoreCartItem objects that require signature.
     */
    private function find_signature_required_items(StorePayPalCart $store_cart): array
    {
        return array_values(array_filter($store_cart->cart_items(), fn($item): bool => $this->item_requires_signature($item)));
    }
    /**
     * Checks if an item requires signature delivery.
     *
     * WooCommerce does not have a standard way to mark products as requiring signature.
     * This method relies entirely on the filter hook for shipping plugins to indicate
     * signature requirements.
     *
     * @param StoreCartItem $item The item to check.
     * @return bool True if signature is required.
     */
    private function item_requires_signature(StoreCartItem $item): bool
    {
        /**
         * Filters whether an item requires signature delivery.
         *
         * Allows shipping plugins to indicate if a product requires signature on delivery,
         * which affects PO Box validation.
         *
         * @since 1.0.0
         *
         * @param bool       $requires_signature Whether signature is required (defaults to false).
         * @param WC_Product $product            The WooCommerce product object.
         * @param CartItem   $item               The cart item.
         *
         * @return bool True if signature delivery is required.
         */
        return apply_filters('woocommerce_paypal_payments_store_sync_item_requires_signature', \false, $item->product(), $item->paypal_item());
    }
    /**
     * Checks if an address line represents a PO Box.
     *
     * @param string $address_line The address line to check.
     * @return bool True if the address is a PO Box.
     */
    private function is_po_box(string $address_line): bool
    {
        $normalized = strtolower(str_replace(array(' ', '.', ','), '', $address_line));
        return strpos($normalized, 'pobox') !== \false;
    }
    /**
     * Scenario 3: Validates that the country code is allowed for shipping.
     *
     * @param string|null $country_code The country code to validate.
     * @return ValidationIssue|null Validation issue if country is not allowed.
     */
    private function validate_country(?string $country_code): ?ValidationIssue
    {
        if (!$country_code) {
            return null;
        }
        if (!$this->is_country_allowed($country_code)) {
            return ValidationIssue::create_shipping_unavailable(sprintf('Shipping to %s is not available', $country_code))->user_message(sprintf('We do not ship to %s.', $this->get_country_name($country_code)))->for_field('shipping_address.country_code')->add_context(ShippingErrorContext::create_shipping_not_available()->destination_country($country_code))->add_resolution(ResolutionOption::create_update_address()->label('Use a different shipping country')->priority(Priority::HIGH));
        }
        return null;
    }
    /**
     * Checks if a country code is allowed for shipping.
     *
     * @param string $country_code The country code to check.
     * @return bool True if shipping is allowed to this country.
     */
    private function is_country_allowed(string $country_code): bool
    {
        $wc_countries = $this->get_wc_countries();
        if (!$wc_countries) {
            return \true;
        }
        if (!$this->is_paypal_supported_country($country_code)) {
            return \false;
        }
        $allowed_countries = $wc_countries->get_shipping_countries();
        if (empty($allowed_countries)) {
            $allowed_countries = $wc_countries->get_allowed_countries();
        }
        return isset($allowed_countries[$country_code]);
    }
    /**
     * Gets the country name for a country code.
     *
     * @param string $country_code The country code.
     * @return string The country name, or the country code if name not found.
     */
    private function get_country_name(string $country_code): string
    {
        $wc_countries = $this->get_wc_countries();
        if (!$wc_countries) {
            return $country_code;
        }
        $countries = $wc_countries->get_countries();
        return $countries[$country_code] ?? $country_code;
    }
    private function get_wc_countries(): ?WC_Countries
    {
        if (!function_exists('WC')) {
            return null;
        }
        // The only place in the class that has a `WC()` dependency.
        $wc = WC();
        return $wc->countries;
    }
    private function is_paypal_supported_country(string $country_code): bool
    {
        return in_array($country_code, self::PAYPAL_SUPPORTED_COUNTRIES, \true);
    }
}
