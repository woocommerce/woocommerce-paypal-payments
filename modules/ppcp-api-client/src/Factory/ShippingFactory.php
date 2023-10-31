<?php
/**
 * The shipping factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ShippingOption;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ShippingFactory
 */
class ShippingFactory {

	/**
	 * The address factory.
	 *
	 * @var AddressFactory
	 */
	private $address_factory;

	/**
	 * The shipping option factory.
	 *
	 * @var ShippingOptionFactory
	 */
	private $shipping_option_factory;

	/**
	 * ShippingFactory constructor.
	 *
	 * @param AddressFactory        $address_factory The address factory.
	 * @param ShippingOptionFactory $shipping_option_factory The shipping option factory.
	 */
	public function __construct( AddressFactory $address_factory, ShippingOptionFactory $shipping_option_factory ) {
		$this->address_factory         = $address_factory;
		$this->shipping_option_factory = $shipping_option_factory;
	}

	/**
	 * Creates a shipping object based off a WooCommerce customer.
	 *
	 * @param \WC_Customer $customer The WooCommerce customer.
	 * @param bool         $with_shipping_options Include WC shipping methods.
	 *
	 * @return Shipping
	 */
	public function from_wc_customer( \WC_Customer $customer, bool $with_shipping_options = false ): Shipping {
		// Replicates the Behavior of \WC_Order::get_formatted_shipping_full_name().
		$full_name = sprintf(
			// translators: %1$s is the first name and %2$s is the second name. wc translation.
			_x( '%1$s %2$s', 'full name', 'woocommerce-paypal-payments' ),
			$customer->get_shipping_first_name(),
			$customer->get_shipping_last_name()
		);
		$address = $this->address_factory->from_wc_customer( $customer );
		return new Shipping(
			$full_name,
			$address,
			$with_shipping_options ? $this->shipping_option_factory->from_wc_cart() : array()
		);
	}

	/**
	 * Creates a Shipping object based off a WooCommerce order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return Shipping
	 */
	public function from_wc_order( \WC_Order $order ): Shipping {
		$full_name = $order->get_formatted_shipping_full_name();
		$address   = $this->address_factory->from_wc_order( $order );
		return new Shipping(
			$full_name,
			$address
		);
	}

	/**
	 * Creates a Shipping object based of from the PayPal JSON response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Shipping
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): Shipping {
		if ( ! isset( $data->name->full_name ) ) {
			throw new RuntimeException(
				__( 'No name was given for shipping.', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->address ) ) {
			throw new RuntimeException(
				__( 'No address was given for shipping.', 'woocommerce-paypal-payments' )
			);
		}
		$address = $this->address_factory->from_paypal_response( $data->address );
		$options = array_map(
			array( $this->shipping_option_factory, 'from_paypal_response' ),
			$data->options ?? array()
		);
		return new Shipping(
			$data->name->full_name,
			$address,
			$options
		);
	}
}
