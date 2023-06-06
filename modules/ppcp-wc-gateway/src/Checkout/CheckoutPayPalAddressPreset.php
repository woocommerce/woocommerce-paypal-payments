<?php
/**
 * Service that fills checkout address fields
 * with address selected via PayPal
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Checkout
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Checkout;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\Session\SessionHandler;

/**
 * Class CheckoutPayPalAddressPreset
 */
class CheckoutPayPalAddressPreset {

	/**
	 * Caches Shipping objects for orders.
	 *
	 * @var array
	 */
	private $shipping_cache = array();

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * CheckoutPayPalAddressPreset constructor.
	 *
	 * @param SessionHandler $session_handler The session handler.
	 */
	public function __construct( SessionHandler $session_handler ) {
		$this->session_handler = $session_handler;
	}

	/**
	 * Filters the checkout fields to replace values if necessary.
	 *
	 * @wp-hook woocommerce_checkout_get_value
	 *
	 * @param string|null $default_value The default value.
	 * @param string      $field_id The field ID.
	 *
	 * @return string|null
	 */
	public function filter_checkout_field( $default_value, $field_id ) {
		if ( ! is_string( $default_value ) ) {
			$default_value = null;
		}

		if ( ! is_string( $field_id ) ) {
			return $default_value;
		}

		return $this->read_preset_for_field( $field_id ) ?? $default_value;
	}

	/**
	 * Returns the value for a checkout field from an PayPal order if given.
	 *
	 * @param string $field_id The ID of the field.
	 *
	 * @return string|null
	 */
	private function read_preset_for_field( string $field_id ) {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return null;
		}

		$shipping = $this->read_shipping_from_order();
		$payer    = $order->payer();

		$address_map     = array(
			'billing_address_1' => 'address_line_1',
			'billing_address_2' => 'address_line_2',
			'billing_postcode'  => 'postal_code',
			'billing_country'   => 'country_code',
			'billing_city'      => 'admin_area_2',
			'billing_state'     => 'admin_area_1',
		);
		$payer_name_map  = array(
			'billing_last_name'  => 'surname',
			'billing_first_name' => 'given_name',
		);
		$payer_map       = array(
			'billing_email' => 'email_address',
		);
		$payer_phone_map = array(
			'billing_phone' => 'national_number',
		);

		if ( array_key_exists( $field_id, $address_map ) && $shipping ) {
			return $shipping->address()->{$address_map[ $field_id ]}() ? $shipping->address()->{$address_map[ $field_id ]}() : null;
		}

		if ( array_key_exists( $field_id, $payer_name_map ) && $payer ) {
			return $payer->name()->{$payer_name_map[ $field_id ]}() ? $payer->name()->{$payer_name_map[ $field_id ]}() : null;
		}

		if ( array_key_exists( $field_id, $payer_map ) && $payer ) {
			return $payer->{$payer_map[ $field_id ]}() ? $payer->{$payer_map[ $field_id ]}() : null;
		}

		if (
			array_key_exists( $field_id, $payer_phone_map )
			&& $payer
			&& $payer->phone()
		) {
			return $payer->phone()->phone()->{$payer_phone_map[ $field_id ]}() ? $payer->phone()->phone()->{$payer_phone_map[ $field_id ]}() : null;
		}

		return null;
	}

	/**
	 * Returns the Shipping object for an order, if given.
	 *
	 * @return Shipping|null
	 */
	private function read_shipping_from_order() {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return null;
		}

		if ( array_key_exists( $order->id(), $this->shipping_cache ) ) {
			return $this->shipping_cache[ $order->id() ];
		}

		$shipping = null;
		foreach ( $order->purchase_units() as $unit ) {
			$shipping = $unit->shipping();
			if ( $shipping ) {
				break;
			}
		}

		$this->shipping_cache[ $order->id() ] = $shipping;

		return $shipping;
	}
}
