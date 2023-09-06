<?php
/**
 * The purchase unit object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer;

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
	 * The sanitizer for this purchase unit output.
	 *
	 * @var PurchaseUnitSanitizer|null
	 */
	private $sanitizer;

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
	 * Sets the custom ID.
	 *
	 * @param string $custom_id The value to set.
	 */
	public function set_custom_id( string $custom_id ): void {
		$this->custom_id = $custom_id;
	}

	/**
	 * Sets the sanitizer for this purchase unit output.
	 *
	 * @param PurchaseUnitSanitizer|null $sanitizer The sanitizer.
	 * @return void
	 */
	public function set_sanitizer( ?PurchaseUnitSanitizer $sanitizer ) {
		$this->sanitizer = $sanitizer;
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
	 * @param bool $sanitize_output Whether output should be sanitized for PayPal consumption.
	 * @param bool $allow_ditch_items Whether to allow items to be ditched.
	 *
	 * @return array
	 */
	public function to_array( bool $sanitize_output = true, bool $allow_ditch_items = true ): array {
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

		$has_ditched_items_breakdown = false;

		if ( $sanitize_output && isset( $this->sanitizer ) ) {
			$purchase_unit               = ( $this->sanitizer->sanitize( $purchase_unit, $allow_ditch_items ) );
			$has_ditched_items_breakdown = $this->sanitizer->has_ditched_items_breakdown();
		}

		return $this->apply_ditch_items_mismatch_filter(
			$has_ditched_items_breakdown,
			$purchase_unit
		);
	}

	/**
	 * Applies the ppcp_ditch_items_breakdown filter.
	 * If true purchase_unit items and breakdown are ditched from PayPal.
	 *
	 * @param bool  $ditched_items_breakdown If the breakdown and items were already ditched.
	 * @param array $purchase_unit The purchase_unit array.
	 * @return array
	 */
	public function apply_ditch_items_mismatch_filter( bool $ditched_items_breakdown, array $purchase_unit ): array {
		/**
		 * The filter can be used to control when the items and totals breakdown are removed from PayPal order info.
		 */
		$ditch = apply_filters( 'ppcp_ditch_items_breakdown', $ditched_items_breakdown, $this );

		if ( $ditch ) {
			unset( $purchase_unit['items'] );
			unset( $purchase_unit['amount']['breakdown'] );

			if ( isset( $this->sanitizer ) && ( $ditch !== $ditched_items_breakdown ) ) {
				$this->sanitizer->set_last_message(
					__( 'Ditch items breakdown filter. Items and breakdown ditched.', 'woocommerce-paypal-payments' )
				);
			}
		}

		return $purchase_unit;
	}
}
