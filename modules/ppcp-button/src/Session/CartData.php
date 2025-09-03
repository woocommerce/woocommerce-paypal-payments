<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Session;

/**
 * Contains a snapshot of the WC cart data, e.g. for saving it and creating an order later.
 */
class CartData {
	protected ?string $key = null;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	protected array $items;

	/**
	 * @var string[]
	 */
	protected array $coupons;

	protected bool $needs_shipping;

	protected string $cart_hash;

	protected ?string $paypal_order_id = null;

	/**
	 * @param array<string, array<string, mixed>> $items The cart items like in $cart->get_cart_for_session() or $cart->get_cart().
	 * @param string[]                            $coupons
	 * @param bool                                $needs_shipping
	 * @param string                              $cart_hash
	 */
	public function __construct(
		array $items,
		array $coupons,
		bool $needs_shipping,
		string $cart_hash
	) {
		$this->items          = $items;
		$this->coupons        = $coupons;
		$this->needs_shipping = $needs_shipping;
		$this->cart_hash      = $cart_hash;
	}

	/**
	 * Generates a new random key.
	 */
	public function generate_key(): void {
		$this->key = uniqid( '', true );
	}

	/**
	 * Returns the key that can be used for identifying the instance in storage.
	 */
	public function key(): ?string {
		return $this->key;
	}

	/**
	 * The cart items like in $cart->get_cart_for_session() or $cart->get_cart().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * @return string[]
	 */
	public function coupons(): array {
		return $this->coupons;
	}

	public function needs_shipping(): bool {
		return $this->needs_shipping;
	}

	public function cart_hash(): string {
		return $this->cart_hash;
	}

	public function set_paypal_order_id( ?string $paypal_order_id ): void {
		$this->paypal_order_id = $paypal_order_id;
	}

	public function paypal_order_id(): ?string {
		return $this->paypal_order_id;
	}

	public function to_array(): array {
		return array(
			'items'           => $this->items,
			'coupons'         => $this->coupons,
			'needs_shipping'  => $this->needs_shipping,
			'cart_hash'       => $this->cart_hash,
			'paypal_order_id' => $this->paypal_order_id,
		);
	}

	public static function from_array( array $data, ?string $key = null ): CartData {
		$cart_data                  = new CartData(
			$data['items'] ?? array(),
			$data['coupons'] ?? array(),
			(bool) ( $data['needs_shipping'] ?? false ),
			$data['cart_hash'] ?? ''
		);
		$cart_data->paypal_order_id = $data['paypal_order_id'] ?? null;
		$cart_data->key             = $key;
		return $cart_data;
	}
}
