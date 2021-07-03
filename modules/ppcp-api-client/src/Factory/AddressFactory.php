<?php
/**
 * The Address factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class AddressFactory
 */
class AddressFactory {

	/**
	 * Returns either the shipping or billing Address object of a customer.
	 *
	 * @param \WC_Customer $customer The WooCommerce customer.
	 * @param string       $type Either 'shipping' or 'billing'.
	 *
	 * @return Address
	 */
	public function from_wc_customer( \WC_Customer $customer, string $type = 'shipping' ): Address {
		return new Address(
			( 'shipping' === $type ) ?
				$customer->get_shipping_country() : $customer->get_billing_country(),
			( 'shipping' === $type ) ?
				$customer->get_shipping_address_1() : $customer->get_billing_address_1(),
			( 'shipping' === $type ) ?
				$customer->get_shipping_address_2() : $customer->get_billing_address_2(),
			( 'shipping' === $type ) ?
				$customer->get_shipping_state() : $customer->get_billing_state(),
			( 'shipping' === $type ) ?
				$customer->get_shipping_city() : $customer->get_billing_city(),
			( 'shipping' === $type ) ?
				$customer->get_shipping_postcode() : $customer->get_billing_postcode()
		);
	}

	/**
	 * Returns an Address object based of a WooCommerce order.
	 *
	 * @param \WC_Order $order The order.
	 *
	 * @return Address
	 */
	public function from_wc_order( \WC_Order $order ): Address {
		return new Address(
			$order->get_shipping_country(),
			$order->get_shipping_address_1(),
			$order->get_shipping_address_2(),
			$order->get_shipping_state(),
			$order->get_shipping_city(),
			$order->get_shipping_postcode()
		);
	}

	/**
	 * Creates an Address object based off a PayPal Response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Address
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): Address {
		if ( ! isset( $data->country_code ) ) {
			throw new RuntimeException(
				__( 'No country given for address.', 'woocommerce-paypal-payments' )
			);
		}
		return new Address(
			$data->country_code,
			( isset( $data->address_line_1 ) ) ? $data->address_line_1 : '',
			( isset( $data->address_line_2 ) ) ? $data->address_line_2 : '',
			( isset( $data->admin_area_1 ) ) ? $data->admin_area_1 : '',
			( isset( $data->admin_area_2 ) ) ? $data->admin_area_2 : '',
			( isset( $data->postal_code ) ) ? $data->postal_code : ''
		);
	}
}
