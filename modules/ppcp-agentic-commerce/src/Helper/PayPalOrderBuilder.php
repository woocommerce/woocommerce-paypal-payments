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
	 * @param PayPalCart $cart          The PayPal cart.
	 * @param CartData   $woo_cart_data The translated cart data.
	 * @return PurchaseUnit
	 */
	public function build_purchase_unit_from_cart(
		PayPalCart $cart,
		CartData $woo_cart_data
	): PurchaseUnit {

		$cart_items = $woo_cart_data->items();

		// TODO: Why not using the PayPalCart to calculate the total?
		$total = 0.0;
		foreach ( $cart_items as $item ) {
			$total += (float) $item['line_total'];
		}

		// TODO: Why do we use Woo currency instead of the PayPalCart currency?
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
		$total_amount = new Money( $total, $currency );
		$breakdown    = new AmountBreakdown( $total_amount );
		$amount       = new Amount( $total_amount, $breakdown );
		$shipping     = $this->build_shipping_from_cart( $cart );

		return new PurchaseUnit( $amount, $items, $shipping );
	}

	public function build_shipping_from_cart( PayPalCart $cart ): ?Shipping {
		$full_name = CartHelper::full_customer_name( $cart );
		$shipping  = CartHelper::shipping_address_array( $cart );

		if ( ! $full_name || ! $shipping['country_code'] ) {
			return null;
		}

		$address = new Address(
			$shipping['country_code'],
			$shipping['address_line_1'],
			$shipping['address_line_2'],
			$shipping['admin_area_2'],
			$shipping['admin_area_1'],
			$shipping['postal_code'],
		);

		return new Shipping( $full_name, $address );
	}
}
