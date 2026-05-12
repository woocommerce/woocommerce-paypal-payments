<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync;

use InvalidArgumentException;

/**
 * Fluent builder for cart payloads.
 */
class CartPayloadBuilder {
	/**
	 * Cart items.
	 *
	 * @var array
	 */
	private array $items = array();

	/**
	 * Shipping information.
	 *
	 * @var array|null
	 */
	private ?array $shipping = null;

	/**
	 * Payment method type.
	 *
	 * @var string
	 */
	private string $payment_type = 'paypal';

	/**
	 * Add a cart item.
	 *
	 * @param string $item_id  Item ID.
	 * @param int    $quantity Quantity.
	 * @param string $value    Price value.
	 * @param string $currency Currency code.
	 * @return self
	 */
	public function with_item(
		string $item_id = 'TEST-001',
		int $quantity = 1,
		string $value = '25.00',
		string $currency = 'USD'
	): self {
		$this->items[] = array(
			'item_id'  => $item_id,
			'quantity' => $quantity,
			'price'    => array(
				'currency_code' => $currency,
				'value'         => $value,
			),
		);

		return $this;
	}

	/**
	 * Add a cart item using a named fixture.
	 *
	 * @param string $fixture_name Fixture name.
	 * @return self
	 */
	public function with_item_fixture( string $fixture_name ): self {
		$fixtures = array(
			'default'    => array( 'TEST-001', 1, '25.00', 'USD' ),
			'expensive'  => array( 'TEST-002', 1, '99.99', 'USD' ),
			'multi'      => array( 'TEST-003', 5, '10.00', 'USD' ),
			'old_item'   => array( 'OLD-ITEM-001', 1, '25.00', 'USD' ),
			'new_item_2' => array( 'NEW-ITEM-002', 3, '50.00', 'USD' ),
			'new_item_3' => array( 'NEW-ITEM-003', 1, '30.00', 'USD' ),
			'eur_item'   => array( 'NEW-ITEM-002', 5, '99.99', 'EUR' ),
		);

		if ( ! isset( $fixtures[ $fixture_name ] ) ) {
			throw new InvalidArgumentException( "Unknown item fixture: {$fixture_name}" );
		}

		return $this->with_item( ...$fixtures[ $fixture_name ] );
	}

	/**
	 * Add US shipping address.
	 *
	 * @param string $address Street address.
	 * @param string $city    City.
	 * @param string $state   State code.
	 * @param string $zip     ZIP code.
	 * @return self
	 */
	public function with_us_shipping(
		string $address = '123 Main St',
		string $city = 'San Jose',
		string $state = 'CA',
		string $zip = '95131'
	): self {
		$this->shipping = array(
			'address_line_1' => $address,
			'admin_area_2'   => $city,
			'admin_area_1'   => $state,
			'postal_code'    => $zip,
			'country_code'   => 'US',
		);

		return $this;
	}

	/**
	 * Add German shipping address.
	 *
	 * @param string $address Street address.
	 * @param string $city    City.
	 * @param string $state   State code.
	 * @param string $zip     ZIP code.
	 * @return self
	 */
	public function with_de_shipping(
		string $address = '456 New Ave',
		string $city = 'Berlin',
		string $state = 'BE',
		string $zip = '10115'
	): self {
		$this->shipping = array(
			'address_line_1' => $address,
			'admin_area_2'   => $city,
			'admin_area_1'   => $state,
			'postal_code'    => $zip,
			'country_code'   => 'DE',
		);

		return $this;
	}

	/**
	 * Add shipping using a named fixture.
	 *
	 * @param string $fixture_name Fixture name.
	 * @return self
	 */
	public function with_shipping_fixture( string $fixture_name ): self {
		$fixtures = array(
			'us_default' => array( 'with_us_shipping', array() ),
			'us_john'    => array( 'with_us_shipping', array( '123 Main St' ) ),
			'us_old'     => array( 'with_us_shipping', array( '123 Old St' ) ),
			'de_default' => array( 'with_de_shipping', array() ),
			'de_new'     => array( 'with_de_shipping', array( '456 New Ave' ) ),
		);

		if ( ! isset( $fixtures[ $fixture_name ] ) ) {
			throw new InvalidArgumentException( "Unknown shipping fixture: {$fixture_name}" );
		}

		list( $method, $args ) = $fixtures[ $fixture_name ];

		return $this->$method( ...$args );
	}

	/**
	 * Build and return the cart payload as an array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$cart = array(
			'items'          => $this->items,
			'payment_method' => array( 'type' => $this->payment_type ),
		);

		if ( $this->shipping ) {
			$cart['shipping_address'] = $this->shipping;
		}

		return $cart;
	}
}
