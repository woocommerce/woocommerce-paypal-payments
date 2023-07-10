<?php
/**
 * The item object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Item
 */
class Item {

	const PHYSICAL_GOODS = 'PHYSICAL_GOODS';
	const DIGITAL_GOODS  = 'DIGITAL_GOODS';

	/**
	 * The name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The unit amount.
	 *
	 * @var Money
	 */
	private $unit_amount;

	/**
	 * The quantity.
	 *
	 * @var int
	 */
	private $quantity;

	/**
	 * The description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The tax.
	 *
	 * @var Money|null
	 */
	private $tax;

	/**
	 * The SKU.
	 *
	 * @var string
	 */
	private $sku;

	/**
	 * The category.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * The tax rate.
	 *
	 * @var float
	 */
	protected $tax_rate;

	/**
	 * The cart item key.
	 *
	 * @var string|null
	 */
	protected $cart_item_key;

	/**
	 * Item constructor.
	 *
	 * @param string     $name The name.
	 * @param Money      $unit_amount The unit amount.
	 * @param int        $quantity The quantity.
	 * @param string     $description The description.
	 * @param Money|null $tax The tax.
	 * @param string     $sku The SKU.
	 * @param string     $category The category.
	 * @param float      $tax_rate The tax rate.
	 * @param ?string    $cart_item_key The cart key for this item.
	 */
	public function __construct(
		string $name,
		Money $unit_amount,
		int $quantity,
		string $description = '',
		Money $tax = null,
		string $sku = '',
		string $category = 'PHYSICAL_GOODS',
		float $tax_rate = 0,
		string $cart_item_key = null
	) {

		$this->name          = $name;
		$this->unit_amount   = $unit_amount;
		$this->quantity      = $quantity;
		$this->description   = $description;
		$this->tax           = $tax;
		$this->sku           = $sku;
		$this->category      = ( self::DIGITAL_GOODS === $category ) ? self::DIGITAL_GOODS : self::PHYSICAL_GOODS;
		$this->category      = $category;
		$this->tax_rate      = $tax_rate;
		$this->cart_item_key = $cart_item_key;
	}

	/**
	 * Returns the name of the item.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Returns the unit amount.
	 *
	 * @return Money
	 */
	public function unit_amount(): Money {
		return $this->unit_amount;
	}

	/**
	 * Returns the quantity.
	 *
	 * @return int
	 */
	public function quantity(): int {
		return $this->quantity;
	}

	/**
	 * Returns the description.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Returns the tax.
	 *
	 * @return Money|null
	 */
	public function tax() {
		return $this->tax;
	}

	/**
	 * Returns the SKU.
	 *
	 * @return string
	 */
	public function sku() {
		return $this->sku;
	}

	/**
	 * Returns the category.
	 *
	 * @return string
	 */
	public function category() {
		return $this->category;
	}

	/**
	 * Returns the tax rate.
	 *
	 * @return float
	 */
	public function tax_rate():float {
		return round( (float) $this->tax_rate, 2 );
	}

	/**
	 * Returns the cart key for this item.
	 *
	 * @return string|null
	 */
	public function cart_item_key():?string {
		return $this->cart_item_key;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array() {
		$item = array(
			'name'        => $this->name(),
			'unit_amount' => $this->unit_amount()->to_array(),
			'quantity'    => $this->quantity(),
			'description' => $this->description(),
			'sku'         => $this->sku(),
			'category'    => $this->category(),
		);

		if ( $this->tax() ) {
			$item['tax'] = $this->tax()->to_array();
		}

		if ( $this->tax_rate() ) {
			$item['tax_rate'] = (string) $this->tax_rate();
		}

		if ( $this->cart_item_key() ) {
			$item['cart_item_key'] = (string) $this->cart_item_key();
		}

		return $item;
	}
}
