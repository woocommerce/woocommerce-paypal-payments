<?php
/**
 * Abstract class for cart Endpoints.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;

/**
 * Abstract Class AbstractCartEndpoint
 */
abstract class AbstractCartEndpoint implements EndpointInterface {

	const ENDPOINT = '';

	/**
	 * The current cart object.
	 *
	 * @var \WC_Cart
	 */
	protected $cart;

	/**
	 * The product data store.
	 *
	 * @var \WC_Data_Store
	 */
	protected $product_data_store;

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	protected $request_data;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The tag to be added to logs.
	 *
	 * @var string
	 */
	protected $logger_tag = '';

	/**
	 * The added cart item IDs
	 *
	 * @var array
	 */
	private $cart_item_keys = array();

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return static::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {
		try {
			return $this->handle_data();
		} catch ( Exception $error ) {
			$this->logger->error( 'Cart ' . $this->logger_tag . ' failed: ' . $error->getMessage() );

			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
					'code'    => $error->getCode(),
					'details' => is_a( $error, PayPalApiException::class ) ? $error->details() : array(),
				)
			);
			return false;
		}
	}

	/**
	 * Handles the request data.
	 *
	 * @return bool
	 * @throws Exception On error.
	 */
	abstract protected function handle_data(): bool;

	/**
	 * Adds products to cart.
	 *
	 * @param array $products Array of products to be added to cart.
	 * @return bool
	 * @throws Exception Add to cart methods throw an exception on fail.
	 */
	protected function add_products( array $products ): bool {
		$this->cart->empty_cart( false );

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
			$this->handle_error();
		}

		return $success;
	}

	/**
	 * Handles errors.
	 *
	 * @param bool $send_response If this error handling should return the response.
	 * @return void
	 */
	protected function handle_error( bool $send_response = true ): void {

		$message = __(
			'Something went wrong. Action aborted',
			'woocommerce-paypal-payments'
		);
		$errors  = wc_get_notices( 'error' );
		if ( count( $errors ) ) {
			$message = array_reduce(
				$errors,
				static function ( string $add, array $error ): string {
					return $add . $error['notice'] . ' ';
				},
				''
			);
			wc_clear_notices();
		}

		if ( $send_response ) {
			wp_send_json_error(
				array(
					'name'    => '',
					'message' => $message,
					'code'    => 0,
					'details' => array(),
				)
			);
		}
	}

	/**
	 * Returns product information from request data.
	 *
	 * @return array|false
	 */
	protected function products_from_request() {
		$data     = $this->request_data->read_request( $this->nonce() );
		$products = $this->products_from_data( $data );
		if ( ! $products ) {
			wp_send_json_error(
				array(
					'name'    => '',
					'message' => __(
						'Necessary fields not defined. Action aborted.',
						'woocommerce-paypal-payments'
					),
					'code'    => 0,
					'details' => array(),
				)
			);
			return false;
		}

		return $products;
	}

	/**
	 * Returns product information from a data array.
	 *
	 * @param array $data The data array.
	 *
	 * @return array|null
	 */
	protected function products_from_data( array $data ): ?array {

		$products = array();

		if (
			! isset( $data['products'] )
			|| ! is_array( $data['products'] )
		) {
			return null;
		}
		foreach ( $data['products'] as $product ) {
			if ( ! isset( $product['quantity'] ) || ! isset( $product['id'] ) ) {
				return null;
			}

			$wc_product = wc_get_product( (int) $product['id'] );

			if ( ! $wc_product ) {
				return null;
			}
			$products[] = array(
				'product'    => $wc_product,
				'quantity'   => (int) $product['quantity'],
				'variations' => $product['variations'] ?? null,
				'booking'    => $product['booking'] ?? null,
				'extra'      => $product['extra'] ?? null,
			);
		}
		return $products;
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
	private function add_product( \WC_Product $product, int $quantity ): bool {
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
	private function add_variable_product(
		\WC_Product $product,
		int $quantity,
		array $post_variations
	): bool {

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
	private function add_booking_product(
		\WC_Product $product,
		array $data
	): bool {

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
	 */
	protected function remove_cart_items(): void {
		foreach ( $this->cart_item_keys as $cart_item_key ) {
			if ( ! $cart_item_key ) {
				continue;
			}
			$this->cart->remove_cart_item( $cart_item_key );
		}
	}

}
