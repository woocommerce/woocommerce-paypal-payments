<?php
/**
 * The purchase unit object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PurchaseUnit
 */
class PurchaseUnit {

	/**
	 * The amount.
	 *
	 * @var Amount
	 */
	private $amount;

	/**
	 * The Items.
	 *
	 * @var Item[]
	 */
	private $items;

	/**
	 * The shipping.
	 *
	 * @var Shipping|null
	 */
	private $shipping;

	/**
	 * The reference id.
	 *
	 * @var string
	 */
	private $reference_id;

	/**
	 * The description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The Payee.
	 *
	 * @var Payee|null
	 */
	private $payee;

	/**
	 * The custom id.
	 *
	 * @var string
	 */
	private $custom_id;

	/**
	 * The invoice id.
	 *
	 * @var string
	 */
	private $invoice_id;

	/**
	 * The soft descriptor.
	 *
	 * @var string
	 */
	private $soft_descriptor;

	/**
	 * The Payments.
	 *
	 * @var Payments|null
	 */
	private $payments;

	/**
	 * Whether the unit contains physical goods.
	 *
	 * @var bool
	 */
	private $contains_physical_goods = false;

	/**
	 * PurchaseUnit constructor.
	 *
	 * @param Amount        $amount The Amount.
	 * @param Item[]        $items The Items.
	 * @param Shipping|null $shipping The Shipping.
	 * @param string        $reference_id The reference ID.
	 * @param string        $description The description.
	 * @param Payee|null    $payee The Payee.
	 * @param string        $custom_id The custom ID.
	 * @param string        $invoice_id The invoice ID.
	 * @param string        $soft_descriptor The soft descriptor.
	 * @param Payments|null $payments The Payments.
	 */
	public function __construct(
		Amount $amount,
		array $items = array(),
		Shipping $shipping = null,
		string $reference_id = 'default',
		string $description = '',
		Payee $payee = null,
		string $custom_id = '',
		string $invoice_id = '',
		string $soft_descriptor = '',
		Payments $payments = null
	) {

		$this->amount       = $amount;
		$this->shipping     = $shipping;
		$this->reference_id = $reference_id;
		$this->description  = $description;
        //phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
		$this->items           = array_values(
			array_filter(
				$items,
				function ( $item ): bool {
					$is_item = is_a( $item, Item::class );
					/**
					 * The item.
					 *
					 * @var Item $item
					 */
					if ( $is_item && Item::PHYSICAL_GOODS === $item->category() ) {
						$this->contains_physical_goods = true;
					}

					return $is_item;
				}
			)
		);
		$this->payee           = $payee;
		$this->custom_id       = $custom_id;
		$this->invoice_id      = $invoice_id;
		$this->soft_descriptor = $soft_descriptor;
		$this->payments        = $payments;
	}

	/**
	 * Returns the amount.
	 *
	 * @return Amount
	 */
	public function amount(): Amount {
		return $this->amount;
	}

	/**
	 * Returns the shipping.
	 *
	 * @return Shipping|null
	 */
	public function shipping() {
		return $this->shipping;
	}

	/**
	 * Returns the reference id.
	 *
	 * @return string
	 */
	public function reference_id(): string {
		return $this->reference_id;
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
	 * Returns the custom id.
	 *
	 * @return string
	 */
	public function custom_id(): string {
		return $this->custom_id;
	}

	/**
	 * Returns the invoice id.
	 *
	 * @return string
	 */
	public function invoice_id(): string {
		return $this->invoice_id;
	}

	/**
	 * Returns the soft descriptor.
	 *
	 * @return string
	 */
	public function soft_descriptor(): string {
		return $this->soft_descriptor;
	}

	/**
	 * Returns the Payee.
	 *
	 * @return Payee|null
	 */
	public function payee() {
		return $this->payee;
	}

	/**
	 * Returns the Payments.
	 *
	 * @return Payments|null
	 */
	public function payments() {
		return $this->payments;
	}

	/**
	 * Returns the Items.
	 *
	 * @return Item[]
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Whether the unit contains physical goods.
	 *
	 * @return bool
	 */
	public function contains_physical_goods(): bool {
		return $this->contains_physical_goods;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$purchase_unit = array(
			'reference_id' => $this->reference_id(),
			'amount'       => $this->amount()->to_array(),
			'description'  => $this->description(),
			'items'        => array_map(
				static function ( Item $item ): array {
					return $item->to_array();
				},
				$this->items()
			),
		);
		if ( $this->ditch_items_when_mismatch( $this->amount(), ...$this->items() ) ) {
			unset( $purchase_unit['items'] );
			unset( $purchase_unit['amount']['breakdown'] );
		}

		if ( $this->payee() ) {
			$purchase_unit['payee'] = $this->payee()->to_array();
		}

		if ( $this->payments() ) {
			$purchase_unit['payments'] = $this->payments()->to_array();
		}

		if ( $this->shipping() ) {
			$purchase_unit['shipping'] = $this->shipping()->to_array();
		}
		if ( $this->custom_id() ) {
			$purchase_unit['custom_id'] = $this->custom_id();
		}
		if ( $this->invoice_id() ) {
			$purchase_unit['invoice_id'] = $this->invoice_id();
		}
		if ( $this->soft_descriptor() ) {
			$purchase_unit['soft_descriptor'] = $this->soft_descriptor();
		}
		return $purchase_unit;
	}

	/**
	 * All money values send to PayPal can only have 2 decimal points. WooCommerce internally does
	 * not have this restriction. Therefore the totals of the cart in WooCommerce and the totals
	 * of the rounded money values of the items, we send to PayPal, can differ. In those cases,
	 * we can not send the line items.
	 *
	 * @param Amount $amount The amount.
	 * @param Item   ...$items The items.
	 * @return bool
	 */
	private function ditch_items_when_mismatch( Amount $amount, Item ...$items ): bool {
		$fee_items_total = ( $amount->breakdown() && $amount->breakdown()->item_total() ) ?
			$amount->breakdown()->item_total()->value() : null;
		$fee_tax_total   = ( $amount->breakdown() && $amount->breakdown()->tax_total() ) ?
			$amount->breakdown()->tax_total()->value() : null;

		foreach ( $items as $item ) {
			if ( null !== $fee_items_total ) {
				$fee_items_total -= $item->unit_amount()->value() * $item->quantity();
			}
			if ( null !== $fee_tax_total ) {
				$fee_tax_total -= $item->tax()->value() * $item->quantity();
			}
		}

		$fee_items_total = round( $fee_items_total, 2 );
		$fee_tax_total   = round( $fee_tax_total, 2 );

		if ( 0.0 !== $fee_items_total || 0.0 !== $fee_tax_total ) {
			return true;
		}

		$breakdown = $this->amount()->breakdown();
		if ( ! $breakdown ) {
			return false;
		}
		$amount_total = 0;
		if ( $breakdown->shipping() ) {
			$amount_total += $breakdown->shipping()->value();
		}
		if ( $breakdown->item_total() ) {
			$amount_total += $breakdown->item_total()->value();
		}
		if ( $breakdown->discount() ) {
			$amount_total -= $breakdown->discount()->value();
		}
		if ( $breakdown->tax_total() ) {
			$amount_total += $breakdown->tax_total()->value();
		}
		if ( $breakdown->shipping_discount() ) {
			$amount_total -= $breakdown->shipping_discount()->value();
		}
		if ( $breakdown->handling() ) {
			$amount_total += $breakdown->handling()->value();
		}
		if ( $breakdown->insurance() ) {
			$amount_total += $breakdown->insurance()->value();
		}

		$amount_value   = $this->amount()->value();
		$needs_to_ditch = (string) $amount_total !== (string) $amount_value;
		return $needs_to_ditch;
	}
}
