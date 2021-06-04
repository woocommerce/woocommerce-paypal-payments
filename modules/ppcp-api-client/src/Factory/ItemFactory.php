<?php
/**
 * The Item factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ItemFactory
 */
class ItemFactory {


	/**
	 * Creates items based off a WooCommerce cart.
	 *
	 * @param \WC_Cart $cart The cart.
	 *
	 * @return Item[]
	 */
	public function from_wc_cart( \WC_Cart $cart ): array {
		$currency = get_woocommerce_currency();
		$items    = array_map(
			static function ( array $item ) use ( $currency ): Item {
				$product = $item['data'];

				/**
				 * The WooCommerce product.
				 *
				 * @var \WC_Product $product
				 */
				$quantity = (int) $item['quantity'];

				$price                     = (float) wc_get_price_including_tax( $product );
				$price_without_tax         = (float) wc_get_price_excluding_tax( $product );
				$price_without_tax_rounded = round( $price_without_tax, 2 );
				$tax                       = round( $price - $price_without_tax_rounded, 2 );
				$tax                       = new Money( $tax, $currency );
				return new Item(
					mb_substr( $product->get_name(), 0, 127 ),
					new Money( $price_without_tax_rounded, $currency ),
					$quantity,
					mb_substr( wp_strip_all_tags( $product->get_description() ), 0, 127 ),
					$tax,
					$product->get_sku(),
					( $product->is_virtual() ) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
				);
			},
			$cart->get_cart_contents()
		);
		return $items;
	}

	/**
	 * Creates Items based off a WooCommerce order.
	 *
	 * @param \WC_Order $order The order.
	 * @return Item[]
	 */
	public function from_wc_order( \WC_Order $order ): array {
		return array_map(
			function ( \WC_Order_Item_Product $item ) use ( $order ): Item {
				return $this->from_wc_order_line_item( $item, $order );
			},
			$order->get_items( 'line_item' )
		);
	}

	/**
	 * Creates an Item based off a WooCommerce Order Item.
	 *
	 * @param \WC_Order_Item_Product $item The WooCommerce order item.
	 * @param \WC_Order              $order The WooCommerce order.
	 *
	 * @return Item
	 */
	private function from_wc_order_line_item( \WC_Order_Item_Product $item, \WC_Order $order ): Item {
		$currency = $order->get_currency();
		$product  = $item->get_product();

		/**
		 * The WooCommerce product.
		 *
		 * @var \WC_Product $product
		 */
		$quantity = (int) $item->get_quantity();

		$price                     = (float) $order->get_item_subtotal( $item, true );
		$price_without_tax         = (float) $order->get_item_subtotal( $item, false );
		$price_without_tax_rounded = round( $price_without_tax, 2 );
		$tax                       = round( $price - $price_without_tax_rounded, 2 );
		$tax                       = new Money( $tax, $currency );
		return new Item(
			mb_substr( $product->get_name(), 0, 127 ),
			new Money( $price_without_tax_rounded, $currency ),
			$quantity,
			mb_substr( wp_strip_all_tags( $product->get_description() ), 0, 127 ),
			$tax,
			$product->get_sku(),
			( $product->is_virtual() ) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
		);
	}

	/**
	 * Creates an Item based off a PayPal response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Item
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): Item {
		if ( ! isset( $data->name ) ) {
			throw new RuntimeException(
				__( 'No name for item given', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->quantity ) || ! is_numeric( $data->quantity ) ) {
			throw new RuntimeException(
				__( 'No quantity for item given', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->unit_amount->value ) || ! isset( $data->unit_amount->currency_code ) ) {
			throw new RuntimeException(
				__( 'No money values for item given', 'woocommerce-paypal-payments' )
			);
		}

		$unit_amount = new Money( (float) $data->unit_amount->value, $data->unit_amount->currency_code );
		$description = ( isset( $data->description ) ) ? $data->description : '';
		$tax         = ( isset( $data->tax ) ) ?
			new Money( (float) $data->tax->value, $data->tax->currency_code )
			: null;
		$sku         = ( isset( $data->sku ) ) ? $data->sku : '';
		$category    = ( isset( $data->category ) ) ? $data->category : 'PHYSICAL_GOODS';

		return new Item(
			$data->name,
			$unit_amount,
			(int) $data->quantity,
			$description,
			$tax,
			$sku,
			$category
		);
	}
}
