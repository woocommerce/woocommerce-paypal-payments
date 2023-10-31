<?php
/**
 * The ShippingOption object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class ShippingOption
 */
class ShippingOption {
	const TYPE_SHIPPING = 'SHIPPING';
	const TYPE_PICKUP   = 'PICKUP';

	/**
	 * The name.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Whether the method is selected by default.
	 *
	 * @var bool
	 */
	private $selected;

	/**
	 * The price.
	 *
	 * @var Money
	 */
	private $amount;

	/**
	 * SHIPPING or PICKUP.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * ShippingOption constructor.
	 *
	 * @param string $id The name.
	 * @param string $label The label.
	 * @param bool   $selected Whether the method is selected by default.
	 * @param Money  $amount The price.
	 * @param string $type SHIPPING or PICKUP.
	 */
	public function __construct( string $id, string $label, bool $selected, Money $amount, string $type ) {
		$this->id       = $id;
		$this->label    = $label;
		$this->selected = $selected;
		$this->amount   = $amount;
		$this->type     = $type;
	}

	/**
	 * The name.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The label.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Whether the method is selected by default.
	 *
	 * @return bool
	 */
	public function selected(): bool {
		return $this->selected;
	}

	/**
	 * Sets whether the method is selected by default.
	 *
	 * @param bool $selected The value to be set.
	 */
	public function set_selected( bool $selected ): void {
		$this->selected = $selected;
	}

	/**
	 * The price.
	 *
	 * @return Money
	 */
	public function amount(): Money {
		return $this->amount;
	}

	/**
	 * SHIPPING or PICKUP.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'       => $this->id,
			'label'    => $this->label,
			'selected' => $this->selected,
			'amount'   => $this->amount->to_array(),
			'type'     => $this->type,
		);
	}
}
