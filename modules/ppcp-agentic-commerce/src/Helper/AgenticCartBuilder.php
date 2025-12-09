<?php
/**
 * Builds WC_Cart instances from agentic data sources.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use Throwable;
use Psr\Log\LoggerInterface;
use WP_Error;
use WC_Cart;
use WC_Customer;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Customer;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Coupon;

class AgenticCartBuilder {
	private ProductManager $product_manager;
	private LoggerInterface $logger;

	public function __construct( ProductManager $product_manager, LoggerInterface $logger ) {
		$this->product_manager = $product_manager;
		$this->logger          = $logger;
	}

	/**
	 * This is a relatively expensive operation, the result should be cached during the request.
	 *
	 * @param PayPalCart $paypal_cart The agentic input cart.
	 * @return WC_Cart|WP_Error Either the populated WooCommerce cart or an error.
	 */
	public function paypal_cart_to_wc_cart( PayPalCart $paypal_cart ) {
		$wc_customer = $this->wc_customer();
		$wc_cart     = $this->wc_cart();

		$result = $this->add_items_to_cart( $wc_cart, $paypal_cart->items() );
		if ( is_wp_error( $result ) ) {
			$this->logger->warning(
				sprintf( 'Failed to convert PayPalCart into WC_Cart: %s', $result->get_error_message() ),
				$result->get_error_data()
			);

			return $result;
		}

		$this->apply_coupons( $wc_cart, $paypal_cart->coupons() );
		$this->set_customer_info( $wc_customer, $paypal_cart->customer() );
		$this->set_addresses( $wc_customer, $paypal_cart );

		$wc_cart->calculate_totals();

		$this->logger->info(
			'Converted PayPalCart to WC_Cart',
			array(
				'cart'     => $wc_cart,
				'customer' => $wc_customer,
			)
		);

		return $wc_cart;
	}

	/**
	 * @param WC_Cart    $wc_cart The WC_Cart to update.
	 * @param CartItem[] $items   Items that should be added to the cart.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function add_items_to_cart( WC_Cart $wc_cart, array $items ) {
		$is_empty = true;
		$errors   = array();

		$wc_cart->empty_cart();

		foreach ( $items as $item ) {
			$product = $this->product_manager->find_product( $item );

			if ( ! $product ) {
				$variant_or_id = $item->variant_id() ?: $item->item_id();
				$errors[]      = sprintf( 'Product not found "%s"', (string) $variant_or_id );
				continue;
			}

			$product_id   = $product->get_parent_id() ?: $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
			$quantity     = $item->quantity();

			$variation = array();
			if ( $variation_id && is_callable( array( $product, 'get_variation_attributes' ) ) ) {
				$variation = $product->get_variation_attributes();
			}

			try {
				$cart_item_key = $wc_cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

				if ( $cart_item_key ) {
					$is_empty = false;
				} else {
					$errors[] = sprintf( 'Failed to add "%s".', $product->get_name() );
				}
			} catch ( Throwable $e ) {
				$errors[] = sprintf( 'Failed to add "%s": %s', $product->get_name(), $e->getMessage() );
			}
		}

		// Only return an error if the cart is still empty.
		if ( $is_empty ) {
			return new WP_Error(
				'no_valid_items',
				'No valid products could be added to cart',
				$errors
			);
		}

		return true;
	}

	/**
	 * @param WC_Cart       $wc_cart The cart to apply coupons to.
	 * @param Coupon[]|null $coupons Coupons provided by the agentic cart.
	 */
	private function apply_coupons( WC_Cart $wc_cart, ?array $coupons ): void {
		if ( ! $coupons ) {
			return;
		}

		foreach ( $coupons as $coupon ) {
			$action = $coupon->action();
			$code   = $coupon->code();

			if ( $action !== 'APPLY' || ! $code ) {
				continue;
			}

			$wc_cart->apply_coupon( $code );
		}
	}

	private function set_customer_info( WC_Customer $wc_customer, ?Customer $customer ): void {
		if ( ! $customer ) {
			return;
		}

		$email = $customer->email_address();
		$name  = $customer->name();

		if ( $email ) {
			$wc_customer->set_billing_email( $email );
		}

		if ( $name ) {
			$wc_customer->set_first_name( $name['given_name'] );
			$wc_customer->set_last_name( $name['surname'] );
		}
	}

	private function set_addresses( WC_Customer $wc_customer, PayPalCart $paypal_cart ): void {
		if ( $paypal_cart->shipping_address() ) {
			$shipping = CartHelper::shipping_address_array( $paypal_cart );
			$wc_customer->set_shipping_first_name( $wc_customer->get_first_name() );
			$wc_customer->set_shipping_last_name( $wc_customer->get_last_name() );
			$wc_customer->set_shipping_address_1( $shipping['address_line_1'] );
			$wc_customer->set_shipping_address_2( $shipping['address_line_2'] );
			$wc_customer->set_shipping_city( $shipping['admin_area_2'] );
			$wc_customer->set_shipping_state( $shipping['admin_area_1'] );
			$wc_customer->set_shipping_postcode( $shipping['postal_code'] );
			$wc_customer->set_shipping_country( $shipping['country_code'] );
		}

		if ( $paypal_cart->billing_address() ) {
			$billing = CartHelper::billing_address_array( $paypal_cart );
			$wc_customer->set_billing_first_name( $wc_customer->get_first_name() );
			$wc_customer->set_billing_last_name( $wc_customer->get_last_name() );
			$wc_customer->set_billing_address_1( $billing['address_line_1'] );
			$wc_customer->set_billing_address_2( $billing['address_line_2'] );
			$wc_customer->set_billing_city( $billing['admin_area_2'] );
			$wc_customer->set_billing_state( $billing['admin_area_1'] );
			$wc_customer->set_billing_postcode( $billing['postal_code'] );
			$wc_customer->set_billing_country( $billing['country_code'] );
		}
	}

	private function wc_cart(): WC_Cart {
		$wc_cart = WC()->cart;

		if ( ! ( $wc_cart instanceof WC_Cart ) ) {
			$wc_cart   = new WC_Cart();
			WC()->cart = $wc_cart;
		}

		return $wc_cart;
	}

	/**
	 * Since WC_Cart has no customer property but directly links details from the global
	 * WC()->customer property to the cart/order, we use the global customer here. This works well
	 * in the agentic module since there is no browser session that might collide with our
	 * changes.
	 *
	 * @return WC_Customer
	 */
	private function wc_customer(): WC_Customer {
		$wc_customer = WC()->customer;

		if ( ! ( $wc_customer instanceof WC_Customer ) ) {
			// Create an in-memory customer - note that it has "is_session" set to false.
			$wc_customer   = new WC_Customer();
			WC()->customer = $wc_customer;
		}

		return $wc_customer;
	}
}
