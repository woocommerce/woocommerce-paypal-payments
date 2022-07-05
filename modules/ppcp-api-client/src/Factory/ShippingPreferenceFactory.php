<?php
/**
 * Returns shipping_preference for the given state.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WC_Cart;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

/**
 * Class ShippingPreferenceFactory
 */
class ShippingPreferenceFactory {

	/**
	 * Returns shipping_preference for the given state.
	 *
	 * @param PurchaseUnit $purchase_unit Thw PurchaseUnit.
	 * @param string       $context The operation context like 'checkout', 'cart'.
	 * @param WC_Cart|null $cart The current cart if relevant.
	 * @param string       $funding_source The funding source (PayPal button) like 'paypal', 'venmo', 'card'.
	 * @return string
	 */
	public function from_state(
		PurchaseUnit $purchase_unit,
		string $context,
		?WC_Cart $cart = null,
		string $funding_source = ''
	): string {
		$contains_physical_goods = $purchase_unit->contains_physical_goods();
		if ( ! $contains_physical_goods ) {
			return ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING;
		}

		$has_shipping              = null !== $purchase_unit->shipping();
		$needs_shipping            = $cart && $cart->needs_shipping();
		$shipping_address_is_fixed = $needs_shipping && 'checkout' === $context;

		if ( $shipping_address_is_fixed ) {
			// Checkout + no address given? Probably something weird happened, like no form validation?
			if ( ! $has_shipping ) {
				return ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING;
			}

			return ApplicationContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS;
		}

		if ( 'card' === $funding_source ) {
			if ( ! $has_shipping ) {
				return ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING;
			}
			// Looks like GET_FROM_FILE does not work for the vaulted card button.
			return ApplicationContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS;
		}

		return ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE;
	}
}
