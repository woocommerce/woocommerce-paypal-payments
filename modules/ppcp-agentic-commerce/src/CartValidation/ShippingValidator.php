<?php
/**
 * Shipping Validator for Agentic Commerce.
 *
 * Validates shipping addresses and restrictions according to WooCommerce settings.
 * Covers three main scenarios:
 * 1. Invalid Shipping Address (completeness, format)
 * 2. PO Box Restriction (signature-required items)
 * 3. Region Restricted (country not allowed)
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidAddress;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ShippingUnavailable;

class ShippingValidator implements ValidatorInterface {

	private ProductManager $product_manager;

	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	public function validate( PayPalCart $cart ) {
		$shipping_address = $cart->shipping_address();

		if ( ! $shipping_address ) {
			return null;
		}

		$issues = array();

		// Scenario 1: Invalid Shipping Address.
		$address_issues = $this->validate_address_completeness( $shipping_address );
		if ( $address_issues ) {
			$issues = array_merge( $issues, $address_issues );
		}

		// Scenario 2: PO Box Restriction.
		$po_box_issue = $this->validate_po_box_restrictions( $cart, $shipping_address );
		if ( $po_box_issue ) {
			$issues[] = $po_box_issue;
		}

		// Scenario 3: Region Restricted.
		$country_issue = $this->validate_country( $shipping_address->country_code() );
		if ( $country_issue ) {
			$issues[] = $country_issue;
		}

		return $issues ?: null;
	}

	/**
	 * Scenario 1: Validates that the address has all required fields and proper formats.
	 *
	 * @param \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Address $address The address to validate.
	 * @return InvalidAddress[] Array of validation issues.
	 */
	private function validate_address_completeness( $address ): array {
		$issues = array();

		if ( ! $address->address_line_1() ) {
			$issues[] = new InvalidAddress(
				'Shipping address is missing street address',
				'Please provide a complete street address.',
				'shipping_address.address_line_1'
			);
		}

		if ( ! $address->admin_area_2() ) {
			$issues[] = new InvalidAddress(
				'Shipping address is missing city',
				'Please provide a city.',
				'shipping_address.admin_area_2'
			);
		}

		$postal_code = $address->postal_code();
		if ( ! $postal_code ) {
			$issues[] = new InvalidAddress(
				'Shipping address is missing postal code',
				'Please provide a postal code.',
				'shipping_address.postal_code'
			);
		} else {
			$postal_validation = $this->validate_postal_code_format( $postal_code, $address->country_code() );
			if ( $postal_validation ) {
				$issues[] = $postal_validation;
			}
		}

		return $issues;
	}

	/**
	 * Validates postal code format based on country using WooCommerce's native validation.
	 *
	 * @param string      $postal_code The postal code to validate.
	 * @param string|null $country_code The country code.
	 * @return InvalidAddress|null Validation issue if format is invalid.
	 */
	private function validate_postal_code_format( string $postal_code, ?string $country_code ): ?InvalidAddress {
		if ( ! $country_code ) {
			return null;
		}

		// Use WooCommerce's native postcode validation.
		if ( ! class_exists( 'WC_Validation' ) ) {
			return null;
		}

		$is_valid = \WC_Validation::is_postcode( $postal_code, $country_code );

		if ( ! $is_valid ) {
			return new InvalidAddress(
				sprintf(
					'Invalid postal code format for %s: %s',
					$country_code,
					$postal_code
				),
				'Please provide a valid postal code.',
				'shipping_address.postal_code'
			);
		}

		return null;
	}

	/**
	 * Scenario 2: Validates PO Box restrictions for items requiring signature delivery.
	 *
	 * @param PayPalCart                                                 $cart The cart to validate.
	 * @param \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Address $address The shipping address.
	 * @return ShippingUnavailable|null Validation issue if PO Box restrictions apply.
	 */
	private function validate_po_box_restrictions( PayPalCart $cart, $address ): ?ShippingUnavailable {
		$address_line = $address->address_line_1();

		if ( ! $address_line || ! $this->is_po_box( $address_line ) ) {
			return null;
		}

		$signature_required_items = $this->find_signature_required_items( $cart );

		if ( ! empty( $signature_required_items ) ) {
			$restricted_items = array_map(
				fn( $item ) => $item->item_id(),
				$signature_required_items
			);

			$context = array(
				'restricted_items'   => $restricted_items,
				'restriction_reason' => 'signature_required',
				'po_box_detected'    => true,
			);

			$resolution_options = array(
				array(
					'action'   => 'UPDATE_ADDRESS',
					'label'    => 'Use street address instead',
					'metadata' => array(
						'priority' => 'high',
					),
				),
				array(
					'action'   => 'REMOVE_ITEM',
					'label'    => 'Remove items requiring signature',
					'metadata' => array(
						'priority' => 'low',
					),
				),
			);

			return new ShippingUnavailable(
				'PO Box delivery not available for this order',
				'This order contains items requiring signature confirmation and cannot be delivered to a PO Box.',
				'shipping_address',
				$context,
				$resolution_options
			);
		}

		return null;
	}

	/**
	 * Finds items in the cart that require signature delivery.
	 *
	 * @param PayPalCart $cart The cart to check.
	 * @return array Array of CartItem objects that require signature.
	 */
	private function find_signature_required_items( PayPalCart $cart ): array {
		return array_values(
			array_filter(
				$cart->items(),
				fn( $item ) => $this->item_requires_signature( $item )
			)
		);
	}

	/**
	 * Checks if an item requires signature delivery.
	 *
	 * WooCommerce does not have a standard way to mark products as requiring signature.
	 * This method relies entirely on the filter hook for shipping plugins to indicate
	 * signature requirements.
	 *
	 * @param \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem $item The item to check.
	 * @return bool True if signature is required.
	 */
	private function item_requires_signature( $item ): bool {
		$product = $this->product_manager->find_product( $item );

		if ( ! $product ) {
			return false;
		}

		/**
		 * Filters whether an item requires signature delivery.
		 *
		 * Allows shipping plugins to indicate if a product requires signature on delivery,
		 * which affects PO Box validation.
		 *
		 * @since 1.0.0
		 *
		 * @param bool       $requires_signature Whether signature is required (defaults to false).
		 * @param \WC_Product $product           The WooCommerce product object.
		 * @param \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem $item The cart item.
		 *
		 * @return bool True if signature delivery is required.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_agentic_commerce_item_requires_signature',
			false,
			$product,
			$item
		);
	}

	/**
	 * Checks if an address line represents a PO Box.
	 *
	 * @param string $address_line The address line to check.
	 * @return bool True if the address is a PO Box.
	 */
	private function is_po_box( string $address_line ): bool {
		$normalized = strtolower( str_replace( array( ' ', '.', ',' ), '', $address_line ) );
		return strpos( $normalized, 'pobox' ) !== false;
	}

	/**
	 * Scenario 3: Validates that the country code is allowed for shipping.
	 *
	 * @param string|null $country_code The country code to validate.
	 * @return ShippingUnavailable|null Validation issue if country is not allowed.
	 */
	private function validate_country( ?string $country_code ): ?ShippingUnavailable {
		if ( ! $country_code ) {
			return null;
		}

		if ( ! $this->is_country_allowed( $country_code ) ) {
			return new ShippingUnavailable(
				sprintf(
					'Shipping to %s is not available',
					$country_code
				),
				sprintf(
					'We do not ship to %s.',
					$this->get_country_name( $country_code )
				),
				'shipping_address.country_code'
			);
		}

		return null;
	}

	/**
	 * Checks if a country code is allowed for shipping.
	 *
	 * @param string $country_code The country code to check.
	 * @return bool True if shipping is allowed to this country.
	 */
	private function is_country_allowed( string $country_code ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return true;
		}

		$wc = WC();
		if ( ! $wc || ! $wc->countries ) {
			return true;
		}

		$allowed_countries = $wc->countries->get_shipping_countries();

		if ( empty( $allowed_countries ) ) {
			$allowed_countries = $wc->countries->get_allowed_countries();
		}

		return isset( $allowed_countries[ $country_code ] );
	}

	/**
	 * Gets the country name for a country code.
	 *
	 * @param string $country_code The country code.
	 * @return string The country name, or the country code if name not found.
	 */
	private function get_country_name( string $country_code ): string {
		if ( ! function_exists( 'WC' ) ) {
			return $country_code;
		}

		$wc = WC();
		if ( ! $wc || ! $wc->countries ) {
			return $country_code;
		}

		$countries = $wc->countries->get_countries();

		return $countries[ $country_code ] ?? $country_code;
	}
}
