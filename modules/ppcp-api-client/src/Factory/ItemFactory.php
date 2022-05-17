<?php
/**
 * The Item factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ItemFactory
 */
class ItemFactory {
	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * ItemFactory constructor.
	 *
	 * @param string $currency 3-letter currency code of the shop.
	 */
	public function __construct( string $currency ) {
		$this->currency = $currency;
	}

	/**
	 * Creates items based off a WooCommerce cart.
	 *
	 * @param \WC_Cart $cart The cart.
	 *
	 * @return Item[]
	 */
	public function from_wc_cart( \WC_Cart $cart ): array {
		$items = array_map(
			function ( array $item ): Item {
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
				$tax                       = new Money( $tax, $this->currency );
				return new Item(
					mb_substr( $product->get_name(), 0, 127 ),
					new Money( $price_without_tax_rounded, $this->currency ),
					$quantity,
					substr( wp_strip_all_tags( $product->get_description() ), 0, 127 ) ?: '',
					$tax,
					$product->get_sku(),
					( $product->is_virtual() ) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
				);
			},
			$cart->get_cart_contents()
		);

		$fees              = array();
		$fees_from_session = WC()->session->get( 'ppcp_fees' );
		if ( $fees_from_session ) {
			$fees = array_map(
				function ( \stdClass $fee ): Item {
					return new Item(
						$fee->name,
						new Money( (float) $fee->amount, $this->currency ),
						1,
						'',
						new Money( (float) $fee->tax, $this->currency )
					);
				},
				$fees_from_session
			);
		}

		return array_merge( $items, $fees );
	}

	/**
	 * Creates Items based off a WooCommerce order.
	 *
	 * @param \WC_Order $order The order.
	 * @return Item[]
	 */
	public function from_wc_order( \WC_Order $order ): array {
		$items = array_map(
			function ( \WC_Order_Item_Product $item ) use ( $order ): Item {
				return $this->from_wc_order_line_item( $item, $order );
			},
			$order->get_items( 'line_item' )
		);

		$fees = array_map(
			function ( \WC_Order_Item_Fee $item ) use ( $order ): Item {
				return $this->from_wc_order_fee( $item, $order );
			},
			$order->get_fees()
		);

		return array_merge( $items, $fees );
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
		/**
		 * The WooCommerce product.
		 *
		 * @var WC_Product $product
		 */
		$product                   = $item->get_product();
		$currency                  = $order->get_currency();
		$quantity                  = (int) $item->get_quantity();
		$price                     = (float) $order->get_item_subtotal( $item, true );
		$price_without_tax         = (float) $order->get_item_subtotal( $item, false );
		$price_without_tax_rounded = round( $price_without_tax, 2 );
		$tax                       = round( $price - $price_without_tax_rounded, 2 );
		$tax                       = new Money( $tax, $currency );

		return new Item(
			mb_substr( $product->get_name(), 0, 127 ),
			new Money( $price_without_tax_rounded, $currency ),
			$quantity,
			substr( wp_strip_all_tags( $product->get_description() ), 0, 127 ) ?: '',
			$tax,
			$product->get_sku(),
			( $product->is_virtual() ) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
		);
	}

	/**
	 * Creates an Item based off a WooCommerce Fee Item.
	 *
	 * @param \WC_Order_Item_Fee $item The WooCommerce order item.
	 * @param \WC_Order          $order The WooCommerce order.
	 *
	 * @return Item
	 */
	private function from_wc_order_fee( \WC_Order_Item_Fee $item, \WC_Order $order ): Item {
		return new Item(
			$item->get_name(),
			new Money( (float) $item->get_amount(), $order->get_currency() ),
			$item->get_quantity(),
			'',
			new Money( (float) $item->get_total_tax(), $order->get_currency() )
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
