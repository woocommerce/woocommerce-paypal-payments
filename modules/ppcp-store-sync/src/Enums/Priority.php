<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Priority levels for resolution options.
 *
 * Used in resolution option metadata to indicate the urgency
 * and recommended order for presenting resolution actions to users.
 */
class Priority {
	public const HIGH   = 'HIGH';
	public const MEDIUM = 'MEDIUM';
	public const LOW    = 'LOW';

	public static function get_all(): array {
		return array(
			self::HIGH,
			self::MEDIUM,
			self::LOW,
		);
	}
}
