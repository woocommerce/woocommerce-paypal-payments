<?php

/**
 * Handles the adding of products to WooCommerce cart.
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use Exception;
use WC_Cart;
use WC_Data_Store;
/**
 * Class CartProductsHelper
 *
 * @phpstan-type AddToCartArgs array{product_id: int, quantity: int, variation_id: int, variation: array, cart_item_data: array}
 */
class CartProductsHelper
{
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
    public function __construct(WC_Data_Store $product_data_store)
    {
        $this->product_data_store = $product_data_store;
    }
    /**
     * Sets a new cart instance.
     *
     * @param WC_Cart $cart The cart.
     * @return void
     */
    public function set_cart(WC_Cart $cart): void
    {
        $this->cart = $cart;
    }
    /**
     * Returns products information from a data array.
     *
     * @param array $data The data array.
     *
     * @return array|null
     */
    public function products_from_data(array $data): ?array
    {
        $products = array();
        if (!isset($data['products']) || !is_array($data['products'])) {
            return null;
        }
        foreach ($data['products'] as $product) {
            $product = $this->product_from_data($product);
            if ($product) {
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
    public function product_from_data(array $product): ?array
    {
        if (!isset($product['quantity']) || !isset($product['id'])) {
            return null;
        }
        $wc_product = wc_get_product((int) $product['id']);
        if (!$wc_product) {
            return null;
        }
        return array('product' => $wc_product, 'quantity' => (int) $product['quantity'], 'variations' => $product['variations'] ?? array(), 'booking' => $product['booking'] ?? null, 'extra' => $product['extra'] ?? null);
    }
    /**
     * Adds products to cart.
     *
     * @param array $products Array of products to be added to cart.
     * @param bool  $validate Whether to run woocommerce_add_to_cart_validation before adding each product.
     * @throws Exception When the cart is not set or a product is not added.
     */
    public function add_products(array $products, bool $validate = \true): bool
    {
        if (!$this->cart) {
            throw new Exception('Cart not set.');
        }
        foreach ($products as $entry) {
            // Add extras to POST, they are usually added by custom plugins.
            if ($entry['extra'] && is_array($entry['extra'])) {
                // Handle cases like field[].
                $query = http_build_query($entry['extra']);
                parse_str($query, $extra);
                foreach ($extra as $key => $value) {
                    $_POST[$key] = $value;
                }
            }
            $args = $this->add_to_cart_args($entry);
            if ($validate && !$this->passes_add_to_cart_validation($args)) {
                throw new Exception('Error adding products to cart.');
            }
            $cart_item_key = $this->cart->add_to_cart($args['product_id'], $args['quantity'], $args['variation_id'], $args['variation'], $args['cart_item_data']);
            if (!$cart_item_key) {
                throw new Exception('Error adding products to cart.');
            }
            $this->cart_item_keys[] = $cart_item_key;
        }
        return \true;
    }
    /**
     * @phpstan-return AddToCartArgs
     * @throws Exception When a booking is added without WC Bookings.
     */
    private function add_to_cart_args(array $entry): array
    {
        $product = $entry['product'];
        $args = array('product_id' => $product->get_id(), 'quantity' => $entry['quantity'], 'variation_id' => 0, 'variation' => array(), 'cart_item_data' => array());
        if ($product->is_type('booking')) {
            $args['quantity'] = 1;
            $args['cart_item_data'] = array('booking' => $this->booking_data($product, is_array($entry['booking']) ? $entry['booking'] : array()));
        } elseif ($product->is_type('variable')) {
            $args['variation'] = $this->variation_attributes(is_array($entry['variations']) ? $entry['variations'] : array());
            // @phpstan-ignore method.notFound
            $args['variation_id'] = $this->product_data_store->find_matching_product_variation($product, $args['variation']);
        }
        return $args;
    }
    /**
     * @param array $post_variations The form's attribute fields, as name/value pairs.
     */
    private function variation_attributes(array $post_variations): array
    {
        $attributes = array();
        foreach ($post_variations as $field) {
            if (!isset($field['name'], $field['value'])) {
                continue;
            }
            $attributes[$field['name']] = $field['value'];
        }
        return $attributes;
    }
    /**
     * @throws Exception When WC Bookings is not active.
     */
    private function booking_data(\WC_Product $product, array $booking_fields): array
    {
        if (!is_callable('wc_bookings_get_posted_data')) {
            // @phpstan-ignore function.impossibleType
            throw new Exception('WooCommerce Bookings is not active.');
        }
        // WC Bookings reads its booking fields from $_POST, so copy them there first.
        foreach ($booking_fields as $key => $value) {
            if (0 === strpos((string) $key, 'wc_bookings_field')) {
                $_POST[$key] = $value;
            }
        }
        return wc_bookings_get_posted_data($booking_fields, $product);
        // @phpstan-ignore function.notFound
    }
    /**
     * @phpstan-param AddToCartArgs $args
     */
    private function passes_add_to_cart_validation(array $args): bool
    {
        /**
         * WooCommerce's add-to-cart validation, which stock and quantity limit plugins hook into.
         *
         * @param bool  $passed_validation Whether the product may be added.
         * @param int   $product_id        The product ID.
         * @param int   $quantity          The quantity to add.
         * @param int   $variation_id      The matched variation ID, or 0 for products without variations.
         * @param array $variation         The variation attributes, or an empty array.
         * @param array $cart_item_data    The cart item data, such as a booking.
         */
        return (bool) apply_filters('woocommerce_add_to_cart_validation', \true, $args['product_id'], $args['quantity'], $args['variation_id'], $args['variation'], $args['cart_item_data']);
    }
    /**
     * Removes stored cart items from WooCommerce cart.
     *
     * @return void
     * @throws Exception Throws if there's a failure removing the cart items.
     */
    public function remove_cart_items(): void
    {
        if (!$this->cart) {
            throw new Exception('Cart not set.');
        }
        foreach ($this->cart_item_keys as $cart_item_key) {
            if (!$cart_item_key) {
                continue;
            }
            $this->cart->remove_cart_item($cart_item_key);
        }
    }
    /**
     * Returns the cart item keys of the items added to cart.
     *
     * @return array
     */
    public function cart_item_keys(): array
    {
        return $this->cart_item_keys;
    }
}
