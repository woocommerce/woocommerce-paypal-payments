<?php
/**
 * Abstract base class for WooCommerce PayPal Payments ability definitions.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\AbilityNames;

/**
 * Shared constants for PayPal Payments ability definitions.
 *
 * Definition classes carry no logic: Woo Core's loader calls their static
 * get_name()/get_registration_args(), and the returned args bind
 * execute_callback/permission_callback to DI-resolved services through
 * AbilityHandlers. Behaviour lives in the Handler services.
 *
 * @internal
 */
abstract class AbstractPpcpAbility {

	/**
	 * Ability category slug.
	 *
	 * @deprecated 4.1.0 Use AbilityNames::CATEGORY_SLUG. Kept as an alias so
	 *             external code reading this constant keeps working.
	 */
	public const CATEGORY_SLUG = AbilityNames::CATEGORY_SLUG;
}
