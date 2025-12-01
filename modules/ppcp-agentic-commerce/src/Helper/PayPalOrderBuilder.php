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
	 * The full purchase unit with proper amounts will be created later
	 * when the WC order is created during checkout.
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

		// Build items for the purchase unit.
		$items = array();
		foreach ( $cart_items as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$product_name        = (string) substr( $product->get_name(), 0, 127 );
			$unit_amount         = new Money( (float) $product->get_price(), $currency );
			$product_description = (string) substr( $product->get_description(), 0, 127 );
			$product_sku         = (string) substr( $product->get_sku(), 0, 127 );
			$product_category    = $product->is_virtual() ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS;

			$items[] = new Item(
				$product_name,
				$unit_amount,
				(int) $cart_item['quantity'],
				$product_description,
				null, // tax.
				$product_sku,
				$product_category
			);
		}

		// Build amount breakdown (required when items are present).
		$breakdown = new AmountBreakdown(
			new Money( $total, $currency ),
			null, // shipping.
			null, // tax_total.
			null, // insurance.
			null, // handling.
			null, // shipping_discount.
			null  // discount.
		);

		// Build amount with breakdown.
		$amount = new Amount( new Money( $total, $currency ), $breakdown );

		// Build shipping if needed.
		$shipping = null;
		if ( $cart->shipping_address() && $cart->customer() ) {
			$shipping = $this->build_shipping_from_cart( $cart );
		}

		return new PurchaseUnit(
			$amount,
			$items,
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
