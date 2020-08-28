<?php
/**
 * The settings object.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\WcGateway\Exception\NotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Class Settings
 */
class Settings implements ContainerInterface {

	public const KEY = 'woocommerce-ppcp-settings';

	/**
	 * The settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Returns the value for an id.
	 *
	 * @param string $id The value identificator.
	 *
	 * @return mixed
	 * @throws NotFoundException When nothing was found.
	 */
	public function get( $id ) {
		if ( ! $this->has( $id ) ) {
			throw new NotFoundException();
		}
		return $this->settings[ $id ];
	}

	/**
	 * Whether a value exists.
	 *
	 * @param string $id The value identificator.
	 *
	 * @return bool
	 */
	public function has( $id ) {
		$this->load();
		return array_key_exists( $id, $this->settings );
	}

	/**
	 * Sets a value.
	 *
	 * @param string $id The value identificator.
	 * @param mixed  $value The value.
	 */
	public function set( $id, $value ) {
		$this->load();
		$this->settings[ $id ] = $value;
	}

	/**
	 * Stores the settings to the database.
	 */
	public function persist() {

		update_option( self::KEY, $this->settings );
	}

	/**
	 * Resets the onboarding.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		$this->load();
		$fields_to_reset = array(
			'enabled',
			'dcc_gateway_enabled',
			'intent',
			'client_id',
			'client_secret',
			'merchant_email',
		);
		foreach ( $fields_to_reset as $id ) {
			$this->settings[ $id ] = null;
		}

		return true;
	}

	/**
	 * Loads the settings.
	 *
	 * @return bool
	 */
	private function load(): bool {

		if ( $this->settings ) {
			return false;
		}
		$this->settings = get_option( self::KEY, array() );

		$defaults = array(
			'title'                         => __( 'PayPal', 'woocommerce-paypal-commerce-gateway' ),
			'description'                   => __(
				'Pay via PayPal.',
				'woocommerce-paypal-commerce-gateway'
			),
			'button_single_product_enabled' => true,
			'button_mini-cart_enabled'      => true,
			'button_cart_enabled'           => true,
			'brand_name'                    => get_bloginfo( 'name' ),
			'dcc_gateway_title'             => __( 'Credit Cards', 'woocommerce-paypal-commerce-gateway' ),
			'dcc_gateway_description'       => __(
				'Pay with your credit card.',
				'woocommerce-paypal-commerce-gateway'
			),
		);
		foreach ( $defaults as $key => $value ) {
			if ( isset( $this->settings[ $key ] ) ) {
				continue;
			}
			$this->settings[ $key ] = $value;
		}
		return true;
	}
}
