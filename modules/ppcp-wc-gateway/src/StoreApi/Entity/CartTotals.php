<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity;

/**
 * CartTotals object for the Store API.
 */
class CartTotals {
	private Money $total_items;

	private Money $total_items_tax;

	private Money $total_fees;

	private Money $total_fees_tax;

	private Money $total_discount;

	private Money $total_discount_tax;

	private Money $total_shipping;

	private Money $total_shipping_tax;

	private Money $total_price;

	private Money $total_tax;

	public function __construct(
		Money $total_items,
		Money $total_items_tax,
		Money $total_fees,
		Money $total_fees_tax,
		Money $total_discount,
		Money $total_discount_tax,
		Money $total_shipping,
		Money $total_shipping_tax,
		Money $total_price,
		Money $total_tax
	) {
		$this->total_items        = $total_items;
		$this->total_items_tax    = $total_items_tax;
		$this->total_fees         = $total_fees;
		$this->total_fees_tax     = $total_fees_tax;
		$this->total_discount     = $total_discount;
		$this->total_discount_tax = $total_discount_tax;
		$this->total_shipping     = $total_shipping;
		$this->total_shipping_tax = $total_shipping_tax;
		$this->total_price        = $total_price;
		$this->total_tax          = $total_tax;
	}

	public function total_items(): Money {
		return $this->total_items;
	}

	public function total_items_tax(): Money {
		return $this->total_items_tax;
	}

	public function total_fees(): Money {
		return $this->total_fees;
	}

	public function total_fees_tax(): Money {
		return $this->total_fees_tax;
	}

	public function total_discount(): Money {
		return $this->total_discount;
	}

	public function total_discount_tax(): Money {
		return $this->total_discount_tax;
	}

	public function total_shipping(): Money {
		return $this->total_shipping;
	}

	public function total_shipping_tax(): Money {
		return $this->total_shipping_tax;
	}

	public function total_price(): Money {
		return $this->total_price;
	}

	public function total_tax(): Money {
		return $this->total_tax;
	}


}
