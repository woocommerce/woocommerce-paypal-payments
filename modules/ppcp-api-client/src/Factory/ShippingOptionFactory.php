<?php
/**
 * The shipping options factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WC_Cart;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ShippingOption;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class ShippingOptionFactory
 */
class ShippingOptionFactory {

	/**
	 * The Money factory.
	 *
	 * @var MoneyFactory
	 */
	private $money_factory;

	/**
	 * ShippingOptionFactory constructor.
	 *
	 * @param MoneyFactory $money_factory The Money factory.
	 */
	public function __construct( MoneyFactory $money_factory ) {
		$this->money_factory = $money_factory;
	}

	/**
	 * Creates an array of ShippingOption objects for the shipping methods available in the cart.
	 *
	 * @param WC_Cart|null $cart The cart.
	 * @return ShippingOption[]
	 */
	public function from_wc_cart( ?WC_Cart $cart = null ): array {
		if ( ! $cart ) {
			$cart = WC()->cart ?? new WC_Cart();
		}

		$cart->calculate_shipping();

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		$chosen_shipping_method  = $chosen_shipping_methods[0] ?? false;

		$packages = WC()->shipping()->get_packages();
		$options  = array();
		foreach ( $packages as $package ) {
			$rates = $package['rates'] ?? array();
			foreach ( $rates as $rate ) {
				if ( ! $rate instanceof \WC_Shipping_Rate ) {
					continue;
				}
				$options[] = new ShippingOption(
					$rate->get_id(),
					$rate->get_label(),
					$rate->get_id() === $chosen_shipping_method,
					new Money(
						(float) $rate->get_cost(),
						get_woocommerce_currency()
					),
					ShippingOption::TYPE_SHIPPING
				);
			}
		}

		if ( ! $chosen_shipping_methods && $options ) {
			$options[0]->set_selected( true );
		}

		return $options;
	}

	/**
	 * Creates a ShippingOption object from the PayPal JSON object.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return ShippingOption
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( stdClass $data ): ShippingOption {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException( 'No id was given for shipping option.' );
		}
		if ( ! isset( $data->amount ) ) {
			throw new RuntimeException( 'Shipping option amount not found' );
		}

		$amount = $this->money_factory->from_paypal_response( $data->amount );
		return new ShippingOption(
			$data->id,
			$data->label ?? '',
			isset( $data->selected ) ? (bool) $data->selected : false,
			$amount,
			$data->type ?? ShippingOption::TYPE_SHIPPING
		);
	}
}
