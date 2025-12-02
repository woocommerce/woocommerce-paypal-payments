<?php
/**
 * PayPal Order Builder for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use WC_Product;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

/**
 * Builds PayPal Order entities (PurchaseUnit, Shipping) from cart data.
 */
class PayPalOrderBuilder {
	/**
	 * Build a PurchaseUnit from cart without a WC order.
	 *
	 * This creates a minimal purchase unit for PayPal order creation.
	 * For agentic commerce, we create orders without items to allow
	 * cart updates via PATCH. Items are added when the WC order is
	 * created during checkout.
	 *
	 * @param PayPalCart $cart      The PayPal cart.
	 * @param CartData   $cart_data The translated cart data.
	 * @return PurchaseUnit
	 */
	public function build_purchase_unit_from_cart(
		PayPalCart $cart,
		CartData $cart_data
	): PurchaseUnit {

		$cart_items = $cart_data->items();

		// Calculate total from cart items.
		$total = 0.0;
		foreach ( $cart_items as $item ) {
			$total += (float) $item['line_total'];
		}

		// Use the WooCommerce currency.
		$currency = get_woocommerce_currency();

		// Build amount without breakdown to allow PATCH updates.
		// PayPal doesn't allow updating orders that have items in breakdown.
		$amount = new Amount( new Money( $total, $currency ), null );

		// Build shipping if needed.
		$shipping = null;
		if ( $cart->shipping_address() && $cart->customer() ) {
			$shipping = $this->build_shipping_from_cart( $cart );
		}

		// Create purchase unit without items to allow cart updates via PATCH.
		return new PurchaseUnit(
			$amount,
			array(),   // No items - allows PATCH updates.
			$shipping,
			'default', // reference_id.
			'',        // description.
			'',        // custom_id - will be set during checkout when WC order is created.
			'',        // invoice_id - will be set during checkout.
			'',        // soft_descriptor.
			null // payee.
		);
	}

	/**
	 * Build shipping entity from cart.
	 *
	 * @param PayPalCart $cart The cart.
	 * @return Shipping|null
	 */
	public function build_shipping_from_cart( PayPalCart $cart ): ?Shipping {
		$customer = $cart->customer();
		$shipping = $cart->shipping_address();

		if ( ! $customer || ! $shipping ) {
			return null;
		}

		$address = new Address(
			$shipping->country_code() ?? '',
			$shipping->address_line_1() ?? '',
			$shipping->address_line_2() ?? '',
			$shipping->admin_area_2() ?? '', // city.
			$shipping->admin_area_1() ?? '', // state.
			$shipping->postal_code() ?? ''
		);

		// Use customer's name for shipping recipient.
		$full_name = '';
		if ( $customer->name() ) {
			$name      = $customer->name();
			$full_name = trim( ( $name['given_name'] ?? '' ) . ' ' . ( $name['surname'] ?? '' ) );
		}

		return new Shipping(
			$full_name,
			$address,
			null,    // email_address.
			null,    // phone_number.
			array()  // options.
		);
	}
}
