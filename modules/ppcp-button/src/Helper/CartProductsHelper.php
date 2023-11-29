<?php
/**
 * Handles the adding of products to WooCommerce cart.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Exception;
use WC_Cart;
use WC_Data_Store;

/**
 * Class CartProductsHelper
 */
class CartProductsHelper {

	/**
	 * The cart
	 *
	 * @var ?WC_Cart
	 */
	private $cart;

	/**
	 * The product data store.
	 *
	 * @var WC_Data_Store
	 */
	protected $product_data_store;

	/**
	 * The added cart item IDs
	 *
	 * @var array
	 */
	private $cart_item_keys = array();

	/**
	 * CheckoutFormSaver constructor.
	 *
	 * @param WC_Data_Store $product_data_store The data store for products.
	 */
	public function __construct(
		WC_Data_Store $product_data_store
	) {
		$this->product_data_store = $product_data_store;
	}

	/**
	 * Sets a new cart instance.
	 *
	 * @param WC_Cart $cart The cart.
	 * @return void
	 */
	public function set_cart( WC_Cart $cart ): void {
		$this->cart = $cart;
	}

	/**
	 * Returns products information from a data array.
	 *
	 * @param array $data The data array.
	 *
	 * @return array|null
	 */
	public function products_from_data( array $data ): ?array {

		$products = array();

		if (
			! isset( $data['products'] )
			|| ! is_array( $data['products'] )
		) {
			return null;
		}
		foreach ( $data['products'] as $product ) {
			$product = $this->product_from_data( $product );
			if ( $product ) {
				$products[] = $product;
			}
		}
		return $products;
	}

	/**
	 * Returns product information from a data array.
	 *
	 * @param array $product The product data array, usually provided by the product page form.
	 * @return array|null
	 */
	public function product_from_data( array $product ): ?array {
		if ( ! isset( $product['quantity'] ) || ! isset( $product['id'] ) ) {
			return null;
		}

		$wc_product = wc_get_product( (int) $product['id'] );

		if ( ! $wc_product ) {
			return null;
		}
		return array(
			'product'    => $wc_product,
			'quantity'   => (int) $product['quantity'],
			'variations' => $product['variations'] ?? null,
			'booking'    => $product['booking'] ?? null,
			'extra'      => $product['extra'] ?? null,
		);
	}

	/**
	 * Adds products to cart.
	 *
	 * @param array $products Array of products to be added to cart.
	 * @return bool
	 * @throws Exception Add to cart methods throw an exception on fail.
	 */
	public function add_products( array $products ): bool {
		$success = true;
		foreach ( $products as $product ) {

			// Add extras to POST, they are usually added by custom plugins.
			if ( $product['extra'] && is_array( $product['extra'] ) ) {
				// Handle cases like field[].
				$query = http_build_query( $product['extra'] );
				parse_str( $query, $extra );

				foreach ( $extra as $key => $value ) {
					$_POST[ $key ] = $value;
				}
			}

			if ( $product['product']->is_type( 'booking' ) ) {
				$success = $success && $this->add_booking_product(
					$product['product'],
					$product['booking']
				);
			} elseif ( $product['product']->is_type( 'variable' ) ) {
				$success = $success && $this->add_variable_product(
					$product['product'],
					$product['quantity'],
					$product['variations']
				);
			} else {
				$success = $success && $this->add_product(
					$product['product'],
					$product['quantity']
				);
			}
		}

		if ( ! $success ) {
			throw new Exception( 'Error adding products to cart.' );
		}

		return true;
	}

	/**
	 * Adds a product to the cart.
	 *
	 * @param \WC_Product $product The Product.
	 * @param int         $quantity The Quantity.
	 *
	 * @return bool
	 * @throws Exception When product could not be added.
	 */
	public function add_product( \WC_Product $product, int $quantity ): bool {
		if ( ! $this->cart ) {
			throw new Exception( 'Cart not set.' );
		}

		$cart_item_key = $this->cart->add_to_cart( $product->get_id(), $quantity );

		if ( $cart_item_key ) {
			$this->cart_item_keys[] = $cart_item_key;
		}
		return false !== $cart_item_key;
	}

	/**
	 * Adds variations to the cart.
	 *
	 * @param \WC_Product $product The Product.
	 * @param int         $quantity The Quantity.
	 * @param array       $post_variations The variations.
	 *
	 * @return bool
	 * @throws Exception When product could not be added.
	 */
	public function add_variable_product(
		\WC_Product $product,
		int $quantity,
		array $post_variations
	): bool {
		if ( ! $this->cart ) {
			throw new Exception( 'Cart not set.' );
		}

		$variations = array();
		foreach ( $post_variations as $key => $value ) {
			$variations[ $value['name'] ] = $value['value'];
		}

		$variation_id = $this->product_data_store->find_matching_product_variation( $product, $variations );

		// ToDo: Check stock status for variation.
		$cart_item_key = $this->cart->add_to_cart(
			$product->get_id(),
			$quantity,
			$variation_id,
			$variations
		);

		if ( $cart_item_key ) {
			$this->cart_item_keys[] = $cart_item_key;
		}
		return false !== $cart_item_key;
	}

	/**
	 * Adds booking to the cart.
	 *
	 * @param \WC_Product $product The Product.
	 * @param array       $data Data used by the booking plugin.
	 *
	 * @return bool
	 * @throws Exception When product could not be added.
	 */
	public function add_booking_product(
		\WC_Product $product,
		array $data
	): bool {
		if ( ! $this->cart ) {
			throw new Exception( 'Cart not set.' );
		}

		if ( ! is_callable( 'wc_bookings_get_posted_data' ) ) {
			return false;
		}

		$cart_item_data = array(
			'booking' => wc_bookings_get_posted_data( $data, $product ),
		);

		$cart_item_key = $this->cart->add_to_cart( $product->get_id(), 1, 0, array(), $cart_item_data );

		if ( $cart_item_key ) {
			$this->cart_item_keys[] = $cart_item_key;
		}
		return false !== $cart_item_key;
	}

	/**
	 * Removes stored cart items from WooCommerce cart.
	 *
	 * @return void
	 * @throws Exception Throws if there's a failure removing the cart items.
	 */
	public function remove_cart_items(): void {
		if ( ! $this->cart ) {
			throw new Exception( 'Cart not set.' );
		}

		foreach ( $this->cart_item_keys as $cart_item_key ) {
			if ( ! $cart_item_key ) {
				continue;
			}
			$this->cart->remove_cart_item( $cart_item_key );
		}
	}

	/**
	 * Returns the cart item keys of the items added to cart.
	 *
	 * @return array
	 */
	public function cart_item_keys(): array {
		return $this->cart_item_keys;
	}

}
