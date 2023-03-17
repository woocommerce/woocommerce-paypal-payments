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

				$price = (float) $item['line_subtotal'] / (float) $item['quantity'];
				return new Item(
					mb_substr( $product->get_name(), 0, 127 ),
					new Money( $price, $this->currency ),
					$quantity,
					$this->prepare_description( $product->get_description() ),
					null,
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
						null
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
		$product                   = $item->get_product();
		$currency                  = $order->get_currency();
		$quantity                  = (int) $item->get_quantity();
		$price_without_tax         = (float) $order->get_item_subtotal( $item, false );
		$price_without_tax_rounded = round( $price_without_tax, 2 );

		return new Item(
			mb_substr( $item->get_name(), 0, 127 ),
			new Money( $price_without_tax_rounded, $currency ),
			$quantity,
			$product instanceof WC_Product ? $this->prepare_description( $product->get_description() ) : '',
			null,
			$product instanceof WC_Product ? $product->get_sku() : '',
			( $product instanceof WC_Product && $product->is_virtual() ) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS
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
			null
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

	/**
	 * Cleanups the description and prepares it for sending to PayPal.
	 *
	 * @param string $description Item description.
	 * @return string
	 */
	protected function prepare_description( string $description ): string {
		$description = strip_shortcodes( wp_strip_all_tags( $description ) );
		return substr( $description, 0, 127 ) ?: '';
	}
}
