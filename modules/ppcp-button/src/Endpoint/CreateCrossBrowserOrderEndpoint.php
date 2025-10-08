<?php
/**
 * The endpoint to create WooCommerce order for cross-browser AppSwitch flows.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class CreateCrossBrowserOrderEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-create-cross-browser-order';

	private RequestData $request_data;

	private CartDataTransientStorage $cart_data_storage;

	private OrderEndpoint $order_endpoint;

	private WooCommerceOrderCreator $wc_order_creator;

	public function __construct(
		RequestData $request_data,
		CartDataTransientStorage $cart_data_storage,
		OrderEndpoint $order_endpoint,
		WooCommerceOrderCreator $wc_order_creator
	) {
		$this->request_data      = $request_data;
		$this->cart_data_storage = $cart_data_storage;
		$this->order_endpoint    = $order_endpoint;
		$this->wc_order_creator  = $wc_order_creator;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	public function handle_request(): bool {
		try {
			$data = $this->request_data->read_request( self::nonce() );

			if ( ! isset( $data['cart_key'] ) ) {
				wp_send_json_error( array( 'message' => 'Cart key missing' ) );
				return false;
			}

			$cart_key  = $data['cart_key'];
			$cart_data = $this->cart_data_storage->get( $cart_key );

			if ( ! $cart_data ) {
				wp_send_json_error( array( 'message' => 'Cart data not found' ) );
				return false;
			}

			$this->cart_data_storage->remove( $cart_data );

			if ( WC()->cart && WC()->cart->get_cart_hash() === $cart_data->cart_hash() ) {
				wp_send_json_success( array( 'message' => 'Cart already current' ) );
				return true;
			}

			$paypal_order_id = $cart_data->paypal_order_id();
			if ( empty( $paypal_order_id ) ) {
				wp_send_json_error( array( 'message' => 'PayPal order ID missing' ) );
				return false;
			}

			$paypal_order = $this->order_endpoint->order( $paypal_order_id );
			$wc_order     = $this->wc_order_creator->create_from_paypal_order( $paypal_order, $cart_data );

			$wc_order->update_meta_data( PayPalGateway::CROSS_BROWSER_APPSWITCH_META_KEY, wc_bool_to_string( true ) );
			$wc_order->save();

			wp_send_json_success(
				array(
					'redirect' => $wc_order->get_checkout_payment_url(),
				)
			);
			return true;

		} catch ( Exception $exception ) {
			wp_send_json_error( array( 'message' => $exception->getMessage() ) );
			return false;
		}
	}
}
