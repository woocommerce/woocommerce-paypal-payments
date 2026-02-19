<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Enums;

/**
 * Specific shipping-related issue codes.
 *
 * Used in the context.specific_issue field when the main error code
 * is SHIPPING_ERROR.
 */
class ShippingIssue {
	public const SHIPPING_ADDRESS_INVALID                  = 'SHIPPING_ADDRESS_INVALID';
	public const INTERNATIONAL_SHIPPING_RESTRICTED         = 'INTERNATIONAL_SHIPPING_RESTRICTED';
	public const MISSING_SHIPPING_ADDRESS                  = 'MISSING_SHIPPING_ADDRESS';
	public const SHIPPING_TO_PO_BOX_NOT_ALLOWED            = 'SHIPPING_TO_PO_BOX_NOT_ALLOWED';
	public const NO_SHIPPING_OPTIONS                       = 'NO_SHIPPING_OPTIONS';
	public const REGION_RESTRICTED                         = 'REGION_RESTRICTED';
	public const OVERSIZED_ITEM_SHIPPING                   = 'OVERSIZED_ITEM_SHIPPING';
	public const HAZARDOUS_MATERIAL_SHIPPING               = 'HAZARDOUS_MATERIAL_SHIPPING';
	public const SHIPPING_ZONE_NOT_COVERED                 = 'SHIPPING_ZONE_NOT_COVERED';
	public const MISSING_COORDINATES_FOR_ENHANCED_DELIVERY = 'MISSING_COORDINATES_FOR_ENHANCED_DELIVERY';

	public static function get_all(): array {
		return array(
			self::SHIPPING_ADDRESS_INVALID,
			self::INTERNATIONAL_SHIPPING_RESTRICTED,
			self::MISSING_SHIPPING_ADDRESS,
			self::SHIPPING_TO_PO_BOX_NOT_ALLOWED,
			self::NO_SHIPPING_OPTIONS,
			self::REGION_RESTRICTED,
			self::OVERSIZED_ITEM_SHIPPING,
			self::HAZARDOUS_MATERIAL_SHIPPING,
			self::SHIPPING_ZONE_NOT_COVERED,
			self::MISSING_COORDINATES_FOR_ENHANCED_DELIVERY,
		);
	}

	public static function is_valid( string $issue ): bool {
		return in_array( $issue, self::get_all(), true );
	}
}
