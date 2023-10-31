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
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;

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
	 * The cart products helper.
	 *
	 * @var CartProductsHelper
	 */
	protected $cart_products;

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

		try {
			$this->cart_products->add_products( $products );
		} catch ( Exception $e ) {
			$this->handle_error();
		}

		return true;
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
		$products = $this->cart_products->products_from_data( $data );
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
	 * Removes stored cart items from WooCommerce cart.
	 *
	 * @return void
	 */
	protected function remove_cart_items(): void {
		$this->cart_products->remove_cart_items();
	}
}
