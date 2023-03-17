<?php
/**
 * The Plan object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

class Plan {

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $product_id;

	/**
	 * @var array
	 */
	private $billing_cycles;

	/**
	 * @var PaymentPreferences
	 */
	private $payment_preferences;

	/**
	 * @var string
	 */
	private $status;

	public function __construct(
		string $id,
		string $name,
		string $product_id,
		array $billing_cycles,
		PaymentPreferences $payment_preferences,
		string $status = ''
	) {
		$this->id                  = $id;
		$this->name                = $name;
		$this->product_id          = $product_id;
		$this->billing_cycles      = $billing_cycles;
		$this->payment_preferences = $payment_preferences;
		$this->status              = $status;
	}

	/**
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function product_id(): string {
		return $this->product_id;
	}

	/**
	 * @return array
	 */
	public function billing_cycles(): array {
		return $this->billing_cycles;
	}

	/**
	 * @return PaymentPreferences
	 */
	public function payment_preferences(): PaymentPreferences {
		return $this->payment_preferences;
	}

	/**
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	public function to_array():array {
		return array(
			'id'          => $this->id(),
			'name'        => $this->name(),
			'product_id' => $this->product_id(),
			'billing_cycles' => $this->billing_cycles(),
			'payment_preferences' => $this->payment_preferences(),
			'status' => $this->status(),
		);
	}
}
