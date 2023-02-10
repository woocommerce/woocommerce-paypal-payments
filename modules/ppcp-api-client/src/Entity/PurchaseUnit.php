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
	 * Sets the amount.
	 *
	 * @param Amount $amount The value to set.
	 */
	public function set_amount( Amount $amount ): void {
		$this->amount = $amount;
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
	 * Sets shipping info.
	 *
	 * @param Shipping|null $shipping The value to set.
	 */
	public function set_shipping( ?Shipping $shipping ): void {
		$this->shipping = $shipping;
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
	 * @param bool $ditch_items_when_mismatch Whether ditch items when mismatch or not.
	 *
	 * @return array
	 */
	public function to_array( bool $ditch_items_when_mismatch = true ): array {
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

		$ditch = $ditch_items_when_mismatch && $this->ditch_items_when_mismatch( $this->amount(), ...$this->items() );
		/**
		 * The filter can be used to control when the items and totals breakdown are removed from PayPal order info.
		 */
		$ditch = apply_filters( 'ppcp_ditch_items_breakdown', $ditch, $this );

		if ( $ditch ) {
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
		$breakdown = $amount->breakdown();
		if ( ! $breakdown ) {
			return false;
		}

		$item_total = $breakdown->item_total();
		if ( $item_total ) {
			$remaining_item_total = array_reduce(
				$items,
				function ( float $total, Item $item ): float {
					return $total - (float) $item->unit_amount()->value_str() * (float) $item->quantity();
				},
				(float) $item_total->value_str()
			);

			$remaining_item_total = round( $remaining_item_total, 2 );

			if ( 0.0 !== $remaining_item_total ) {
				return true;
			}
		}

		$tax_total      = $breakdown->tax_total();
		$items_with_tax = array_filter(
			$this->items,
			function ( Item $item ): bool {
				return null !== $item->tax();
			}
		);
		if ( $tax_total && ! empty( $items_with_tax ) ) {
			$remaining_tax_total = array_reduce(
				$items,
				function ( float $total, Item $item ): float {
					$tax = $item->tax();
					if ( $tax ) {
						$total -= (float) $tax->value_str() * (float) $item->quantity();
					}
					return $total;
				},
				(float) $tax_total->value_str()
			);

			$remaining_tax_total = round( $remaining_tax_total, 2 );

			if ( 0.0 !== $remaining_tax_total ) {
				return true;
			}
		}

		$shipping          = $breakdown->shipping();
		$discount          = $breakdown->discount();
		$shipping_discount = $breakdown->shipping_discount();
		$handling          = $breakdown->handling();
		$insurance         = $breakdown->insurance();

		$amount_total = 0.0;
		if ( $shipping ) {
			$amount_total += (float) $shipping->value_str();
		}
		if ( $item_total ) {
			$amount_total += (float) $item_total->value_str();
		}
		if ( $discount ) {
			$amount_total -= (float) $discount->value_str();
		}
		if ( $tax_total ) {
			$amount_total += (float) $tax_total->value_str();
		}
		if ( $shipping_discount ) {
			$amount_total -= (float) $shipping_discount->value_str();
		}
		if ( $handling ) {
			$amount_total += (float) $handling->value_str();
		}
		if ( $insurance ) {
			$amount_total += (float) $insurance->value_str();
		}

		$amount_str       = $amount->value_str();
		$amount_total_str = ( new Money( $amount_total, $amount->currency_code() ) )->value_str();
		$needs_to_ditch   = $amount_str !== $amount_total_str;
		return $needs_to_ditch;
	}
}
